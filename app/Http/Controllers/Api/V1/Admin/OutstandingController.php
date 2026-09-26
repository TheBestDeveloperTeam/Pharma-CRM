<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;
use App\Core\{Request,Response,TenantContext};
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Payments\OutstandingService;
final class OutstandingController {public function __construct(private OutstandingService $outstanding,private AuthorizationService $authorization){}public function index(Request $request):Response{$ctx=TenantContext::get();$this->authorization->requirePermission($ctx,'payments','view');$filters=['party_ref'=>$ctx->isDistributor()?$ctx->partyRef:$request->query('party_ref'),'from'=>$request->query('from'),'to'=>$request->query('to'),'bucket'=>$request->query('bucket'),'page'=>$request->query('page',1),'per_page'=>$request->query('per_page',25),'sort_by'=>$request->query('sort_by'),'sort_dir'=>$request->query('sort_dir')];$result=$this->outstanding->pageForContext($ctx,$filters);return Response::json(200,$result['rows'],$result['meta']);}}
