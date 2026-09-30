<?php

/*
|--------------------------------------------------------------------------
| Support page texts
|--------------------------------------------------------------------------
|
| Single source of the /assistenza page and of the AI assistant. The ids are
| the anchors of the page. Placeholders in braces are filled from
| config/shop.php by GetSupportSectionsAction, formatted as prices.
|
*/

return [
    [
        'id' => 'spedizioni',
        'title' => 'Spedizioni e consegne',
        'paragraphs' => [
            'Spediamo in tutta Italia, con consegna in 24/48 ore.',
            'La spedizione costa {shipping_cost} ed è gratuita per gli ordini da {free_shipping_threshold} in su.',
        ],
    ],
    [
        'id' => 'resi',
        'title' => 'Resi e rimborsi',
        'paragraphs' => [
            'Puoi restituire un prodotto entro 30 giorni.',
            'Il rimborso arriva sulla carta usata per il pagamento.',
        ],
    ],
    [
        'id' => 'garanzia',
        'title' => 'Garanzia',
        'paragraphs' => [
            'Tutti i prodotti hanno una garanzia di 2 anni.',
        ],
    ],
    [
        'id' => 'contatti',
        'title' => 'Contatti',
        'paragraphs' => [
            'Scrivici ad assistenza@casadelsilicio.example o chiamaci al +39 050 000 0000.',
            'Rispondiamo dal lunedì al venerdì, dalle 9:00 alle 18:00.',
        ],
    ],
];
