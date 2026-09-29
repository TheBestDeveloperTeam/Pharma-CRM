<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Core\{Request, Response, Container, TenantContext};

final class SearchController
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Container::getInstance()->make(\PDO::class);
    }

    /**
     * UTL-001: Global Search across Leads, Parties, Products, Orders, Invoices
     */
    public function search(Request $r): Response
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        $franchiseRef = $ctx->requireFranchise();

        $q = trim((string)$r->query('q', ''));
        if (strlen($q) < 2) {
            return Response::json(200, [
                'query'    => $q,
                'leads'    => [],
                'parties'  => [],
                'products' => [],
                'orders'   => [],
                'invoices' => [],
            ]);
        }

        $like = '%' . $q . '%';

        // 1. Leads
        $stmt = $this->pdo->prepare("SELECT lead_ref, lead_code, firm_name, contact_name, mobile, status FROM leads WHERE franchise_ref = :f AND (firm_name LIKE :q OR contact_name LIKE :q OR mobile LIKE :q OR lead_code LIKE :q) LIMIT 5");
        $stmt->execute([':f' => $franchiseRef, ':q' => $like]);
        $leads = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Parties
        $stmt = $this->pdo->prepare("SELECT party_ref, party_code, firm_name, contact_name, mobile, gstin, status FROM parties WHERE franchise_ref = :f AND (firm_name LIKE :q OR contact_name LIKE :q OR party_code LIKE :q OR mobile LIKE :q OR gstin LIKE :q) LIMIT 5");
        $stmt->execute([':f' => $franchiseRef, ':q' => $like]);
        $parties = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Products
        $stmt = $this->pdo->prepare("SELECT product_ref, sku, product_name, composition, mrp, franchise_rate, status FROM products WHERE franchise_ref = :f AND (product_name LIKE :q OR sku LIKE :q OR composition LIKE :q) LIMIT 5");
        $stmt->execute([':f' => $franchiseRef, ':q' => $like]);
        $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 4. Orders
        $stmt = $this->pdo->prepare("SELECT order_ref, order_no, client_order_ref, status, grand_total, order_date FROM orders WHERE franchise_ref = :f AND (order_no LIKE :q OR client_order_ref LIKE :q) LIMIT 5");
        $stmt->execute([':f' => $franchiseRef, ':q' => $like]);
        $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 5. Invoices
        $stmt = $this->pdo->prepare("SELECT invoice_ref, invoice_no, grand_total, paid_total, status, invoice_date FROM invoices WHERE franchise_ref = :f AND invoice_no LIKE :q LIMIT 5");
        $stmt->execute([':f' => $franchiseRef, ':q' => $like]);
        $invoices = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::json(200, [
            'query'    => $q,
            'leads'    => $leads,
            'parties'  => $parties,
            'products' => $products,
            'orders'   => $orders,
            'invoices' => $invoices,
        ]);
    }
}
