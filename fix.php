<?php
\ = ['agent-e2e.php', 'agent-billing.php', 'agent-dispatch.php', 'agent-payments.php'];
foreach (\ as \) {
    \ = 'e:\Projects\PHP\Pharma-CRM\tests\Agents\\' . \;
    \ = file_get_contents(\);
    
    // Check if we already injected the DB stuff
    if (strpos(\, 'UPDATE franchises SET state_ref') === false) {
        \ = "
            \ = \->make(\App\Core\Database::class);
            \->prepare(\"UPDATE franchises SET state_ref = 'STA-MAH' WHERE franchise_ref = ?\")->execute([\]);
            \->prepare(\"INSERT IGNORE INTO pincodes (pincode, state_ref, district_ref) VALUES (?, 'STA-MAH', 'DST-PNE')\")->execute(['411001']);
        ";
        
        \ = str_replace(
            "'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);",
            "'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);
" . \,
            \
        );
        file_put_contents(\, \);
    }
}
