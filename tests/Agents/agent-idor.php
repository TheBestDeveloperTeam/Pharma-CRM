<?php
return [
    'name'  => 'agent-idor',
    'scope' => 'security',
    'group' => 'crm',
    'steps' => [
        // 1. Sales user cannot view lead assigned to another sales user
        function() {
            $ctxSalesA = new \App\Core\TenantContext(
                orgRef: 'ORG-PLATFORM0000000001',
                franchiseRef: 'FRN-MUMBAI000000000001',
                userRef: 'USR-SALES-ALPHA',
                role: 'SALES',
                scope: 'FRANCHISE',
                partyRef: null,
                requestId: 'REQ-01'
            );

            $leadAssignedToB = [
                'lead_ref'          => 'LED-TEST00000000001',
                'franchise_ref'     => 'FRN-MUMBAI000000000001',
                'assigned_user_ref' => 'USR-SALES-BETA',
            ];

            $canView = \App\Policies\SalesLeadPolicy::canView($ctxSalesA, $leadAssignedToB);
            \Tests\Support\Assert::true(!$canView, 'Sales user A CANNOT view Sales user B lead');

            $canUpdate = \App\Policies\SalesLeadPolicy::canUpdate($ctxSalesA, $leadAssignedToB);
            \Tests\Support\Assert::true(!$canUpdate, 'Sales user A CANNOT update Sales user B lead');
        },
        // 2. Sales user CAN view lead assigned to them
        function() {
            $ctxSalesA = new \App\Core\TenantContext(
                orgRef: 'ORG-PLATFORM0000000001',
                franchiseRef: 'FRN-MUMBAI000000000001',
                userRef: 'USR-SALES-ALPHA',
                role: 'SALES',
                scope: 'FRANCHISE',
                partyRef: null,
                requestId: 'REQ-02'
            );

            $leadAssignedToA = [
                'lead_ref'          => 'LED-TEST00000000002',
                'franchise_ref'     => 'FRN-MUMBAI000000000001',
                'assigned_user_ref' => 'USR-SALES-ALPHA',
            ];

            $canView = \App\Policies\SalesLeadPolicy::canView($ctxSalesA, $leadAssignedToA);
            \Tests\Support\Assert::true($canView, 'Sales user A CAN view own assigned lead');
        },
        // 3. Franchise Admin CAN view all franchise leads
        function() {
            $ctxAdmin = new \App\Core\TenantContext(
                orgRef: 'ORG-PLATFORM0000000001',
                franchiseRef: 'FRN-MUMBAI000000000001',
                userRef: 'USR-FRNADMIN000000001',
                role: 'FRANCHISE_ADMIN',
                scope: 'FRANCHISE',
                partyRef: null,
                requestId: 'REQ-03'
            );

            $leadAssignedToB = [
                'lead_ref'          => 'LED-TEST00000000001',
                'franchise_ref'     => 'FRN-MUMBAI000000000001',
                'assigned_user_ref' => 'USR-SALES-BETA',
            ];

            $canView = \App\Policies\SalesLeadPolicy::canView($ctxAdmin, $leadAssignedToB);
            \Tests\Support\Assert::true($canView, 'Franchise Admin CAN view all leads');
        },
    ]
];
