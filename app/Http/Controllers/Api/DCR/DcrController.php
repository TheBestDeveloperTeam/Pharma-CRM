<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\DCR;
use App\Core\{Request,Response,TenantContext};use App\Domain\Authorization\AuthorizationService;use App\Domain\DCR\DcrService;
final class DcrController {public function __construct(private DcrService $service,private AuthorizationService $auth){}private function ctx():TenantContext{return TenantContext::get();}private function access(TenantContext$c,Request$r):void{$this->service->assertAccess($c,(string)$r->param('ref'));}
 public function index(Request$r):Response{$c=$this->ctx();$this->auth->requirePermission($c,'dcr','view');return Response::json(200,$this->service->list($c,$r->query));}
 public function store(Request$r):Response{$c=$this->ctx();$this->auth->requirePermission($c,'dcr','create');return Response::json(201,$this->service->create($c,$r->all()));}
 public function show(Request$r):Response{$c=$this->ctx();$this->auth->requirePermission($c,'dcr','view');$this->access($c,$r);return Response::json(200,$this->service->detail($c,(string)$r->param('ref')));}
 public function update(Request$r):Response{$c=$this->ctx();$this->auth->requirePermission($c,'dcr','edit');$this->access($c,$r);return Response::json(200,$this->service->update($c,(string)$r->param('ref'),$r->all()));}
 public function status(Request$r):Response{$c=$this->ctx();$s=(string)$r->input('status');$action=match($s){'SUBMITTED'=>'submit','APPROVED'=>'approve','REJECTED'=>'reject',default=>'edit'};$this->auth->requirePermission($c,'dcr',$action);$this->access($c,$r);return Response::json(200,$this->service->transition($c,(string)$r->param('ref'),$s,$r->input('remarks'));}}
