#!/bin/sh
# Renders the demo illustrations (design/illustrations/**/*.svg) to 800x1000 WebP
# in a throwaway Alpine container: no tool is installed on the host or in the project.
# Images are rendered into a temporary directory inside the container and replace the
# existing WebP files only if every conversion succeeds and every file is under 60 KB.
set -eu

cd "$(dirname "$0")/.."

image='alpine:3.20@sha256:d9e853e87e55526f6b2917df91a2115c36dd7c696a35be12163d44e6e2a4b6bc'

if ! command -v docker > /dev/null 2>&1; then
    echo "Docker is not installed or not in PATH." >&2
    exit 1
fi

if ! docker info > /dev/null 2>&1; then
    echo "The Docker daemon is not running or not reachable." >&2
    exit 1
fi

for dir in design/illustrations/products design/illustrations/categories; do
    if ! ls "$dir"/*.svg > /dev/null 2>&1; then
        echo "No SVG files in $dir." >&2
        exit 1
    fi
done

if [ ! -f design/illustrations/fallback.svg ]; then
    echo "Missing design/illustrations/fallback.svg." >&2
    exit 1
fi

docker run --rm \
    -e HOST_UID="$(id -u)" \
    -e HOST_GID="$(id -g)" \
    -v "$PWD":/repo \
    -w /repo \
    "$image" \
    sh -eu -c '
        products_dir=backend/database/seeders/assets/products
        categories_dir=frontend/src/assets/categories
        fallback=frontend/src/assets/fallback.webp
        max_bytes=61440
        work=/tmp/render

        restore_ownership() {
            for path in backend/database/seeders/assets frontend/src/assets; do
                if [ -e "$path" ]; then
                    chown -R "$HOST_UID:$HOST_GID" "$path"
                fi
            done
        }
        trap restore_ownership EXIT

        apk add --no-cache --quiet rsvg-convert libwebp-tools > /dev/null

        mkdir -p "$work/products" "$work/categories"

        render() {
            rsvg-convert --width 800 --height 1000 --output "$work/render.png" "$1"
            cwebp -quiet -q 80 -m 6 "$work/render.png" -o "$2"
        }

        for svg in design/illustrations/products/*.svg; do
            render "$svg" "$work/products/$(basename "$svg" .svg).webp"
        done

        for svg in design/illustrations/categories/*.svg; do
            render "$svg" "$work/categories/$(basename "$svg" .svg).webp"
        done

        render design/illustrations/fallback.svg "$work/fallback.webp"

        oversized=0
        for webp in "$work"/products/*.webp "$work"/categories/*.webp "$work/fallback.webp"; do
            bytes=$(wc -c < "$webp")
            printf "%6d KB  %s\n" $(( (bytes + 1023) / 1024 )) "${webp#"$work"/}"
            if [ "$bytes" -gt "$max_bytes" ]; then
                echo "  over the 60 KB limit" >&2
                oversized=1
            fi
        done

        if [ "$oversized" -ne 0 ]; then
            echo "Some images exceed 60 KB: the existing WebP files were left unchanged." >&2
            exit 1
        fi

        mkdir -p "$products_dir" "$categories_dir"
        rm -f "$products_dir"/*.webp "$categories_dir"/*.webp "$fallback"
        cp "$work"/products/*.webp "$products_dir"/
        cp "$work"/categories/*.webp "$categories_dir"/
        cp "$work/fallback.webp" "$fallback"

        products=$(ls "$products_dir"/*.webp | wc -l)
        categories=$(ls "$categories_dir"/*.webp | wc -l)
        echo "Rendered $products product images, $categories category images and the fallback image."
    '
