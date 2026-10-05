<?php

namespace App\Http\Requests\Assistant;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The conversation of the widget: the last messages, only with the user and
 * assistant roles, the last one from the user. System and tool messages are
 * refused: the instructions live only in the Modelfile. Assistant messages
 * may be longer, but they are capped too, because the client can forge them.
 */
final class SendAssistantMessageRequest extends FormRequest
{
    public const LAST_MESSAGE_MESSAGE = "L'ultimo messaggio della conversazione deve essere del cliente.";

    private const ROLES = ['user', 'assistant'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'messages' => ['required', 'array', 'list', 'min:1', 'max:'.config('assistant.max_messages')],
            'messages.*' => ['required', 'array'],
            'messages.*.role' => ['required', 'string', Rule::in(self::ROLES)],
            'messages.*.content' => ['bail', 'required', 'string', $this->maxLengthForRole(...)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('messages')) {
                    return;
                }

                $messages = $this->input('messages');
                $last = is_array($messages) ? end($messages) : null;

                if (! is_array($last) || ($last['role'] ?? null) !== 'user') {
                    $validator->errors()->add('messages', self::LAST_MESSAGE_MESSAGE);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'messages' => 'messaggi',
            'messages.*' => 'messaggio',
            'messages.*.role' => 'ruolo',
            'messages.*.content' => 'messaggio',
        ];
    }

    /**
     * The validated conversation, with only role and content.
     *
     * @return list<array{role: string, content: string}>
     */
    public function conversation(): array
    {
        return array_map(
            fn (array $message): array => ['role' => $message['role'], 'content' => $message['content']],
            $this->validated('messages'),
        );
    }

    /**
     * 500 characters for a user message, 4000 for an assistant message.
     */
    private function maxLengthForRole(string $attribute, mixed $value, Closure $fail): void
    {
        $role = $this->input(str_replace('.content', '.role', $attribute));

        $max = $role === 'assistant'
            ? config('assistant.max_assistant_message_length')
            : config('assistant.max_message_length');

        if (mb_strlen($value) > $max) {
            $fail("Il messaggio non può superare {$max} caratteri.");
        }
    }
}
