<?php

return [
    'tones' => [
        'insightful' => [
            'label' => 'Insightful brief',
            'prompt' => 'Write with clarity, analysis, and one practical takeaway in every section.',
        ],
        'friendly' => [
            'label' => 'Friendly expert',
            'prompt' => 'Keep the tone warm, approachable, and easy to follow without sounding casual.',
        ],
        'executive' => [
            'label' => 'Executive summary',
            'prompt' => 'Use crisp, strategic language aimed at decision-makers and business leads.',
        ],
        'launch' => [
            'label' => 'Bold launch energy',
            'prompt' => 'Add confident momentum and polished marketing flair without sounding hype-heavy.',
        ],
    ],

    'audiences' => [
        'general' => [
            'label' => 'General readers',
            'prompt' => 'Assume curiosity and interest, but no specialist background knowledge.',
        ],
        'developers' => [
            'label' => 'Developers',
            'prompt' => 'Include technical specificity and practical implementation context where useful.',
        ],
        'founders' => [
            'label' => 'Founders & leaders',
            'prompt' => 'Frame the article around business decisions, leverage, risk, and momentum.',
        ],
        'marketers' => [
            'label' => 'Marketers & content teams',
            'prompt' => 'Focus on positioning, audience resonance, messaging, and campaign usefulness.',
        ],
    ],

    'depths' => [
        'quick' => [
            'label' => 'Quick read',
            'prompt' => 'Aim for roughly 450 to 650 words split across 3 sections.',
            'sections' => 3,
        ],
        'balanced' => [
            'label' => 'Balanced article',
            'prompt' => 'Aim for roughly 700 to 950 words split across 4 sections.',
            'sections' => 4,
        ],
        'deep' => [
            'label' => 'Deep dive',
            'prompt' => 'Aim for roughly 1000 to 1300 words split across 5 sections.',
            'sections' => 5,
        ],
    ],

    'models' => [
        'gpt-5.4' => [
            'label' => 'GPT-5.4',
            'description' => 'Flagship quality for the ultimate final drafts.',
            'subscriber_only' => true,
        ],
        'gpt-5.2' => [
            'label' => 'GPT-5.2',
            'description' => 'Best quality for richer final drafts.',
        ],
        'gpt-5-mini' => [
            'label' => 'GPT-5 mini',
            'description' => 'Balanced speed and quality.',
        ],
    ],
];
