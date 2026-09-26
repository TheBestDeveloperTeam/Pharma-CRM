<?php
declare(strict_types=1);

/* Static contract inventory for CI once PHP is available.  Keep this aligned
 * with the visible frontend ReportDefinition accessor keys. */
return [
    'dashboard' => ['leads'=>['newToday','unassignedCount','slaBreachedCount','slaThresholdHours','slaTrend','conversionSeries'],'follow_ups'=>['dueTodayCount','overdueCount'],'orders'=>['activeCount','territoryViolationCount','cancelledCount','dispatchPendingCount','salesTrend'],'outstanding'=>['available','reason','total','buckets'],'near_expiry'=>['count','stockValue'],'sales_team'=>['available','rows']],
    'reports' => [
        'lead-source'=>['source','totalLeads','converted','conversionPct'], 'response-time'=>['leadName','source','createdDate','respondedAt','durationHours','breached'], 'conversion'=>['stage','count'],
        'sales-team-productivity'=>['name','leadsCount','convertedCount','ordersCount','ordersValue'], 'territory-sales'=>['state','district','orders','totalSales'], 'party-sales'=>['partyName','orders','totalSales'],
        'product-sales'=>['productName','qtySold','freeQty','netSales'], 'scheme-utilization'=>['schemeName','timesApplied','freeQtyGiven','netSales'], 'order-status'=>['status','count','totalValue'],
        'dispatch-pending'=>['invoiceNo','partyName','status','orderDate','total'], 'batch-inventory'=>['productName','batchNo','expiryDate','availableQty','status','stockValue'],
        'near-expiry'=>['productName','batchNo','expiryDate','daysLeft','availableQty','stockValue'], 'territory-violations'=>['invoiceNo','partyName','reason','orderDate'],
        'webhook-failures'=>['source','eventType','receivedAt','status','errorMessage'], 'whatsapp-delivery'=>['recipientName','templateName','status','sentAt'],
        'payment-outstanding'=>['invoiceNumber','partyName','dueDate','balance','bucket','status'],
    ],
];
