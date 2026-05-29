<?php

return [
    /**
     * Static age groups. These replace the old teams-table dependency for intake/user assignment.
     * Keep users.team_name as the stored value for now to avoid breaking existing code.
     */
    'age_groups' => [
        'u13' => 'U13',
        'u14' => 'U14',
        'u15' => 'U15',
        'u16' => 'U16',
        'u17' => 'U17',
        'u18' => 'U18',
        'u19' => 'U19',
    ],
];
