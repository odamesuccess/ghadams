<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
class InstallAssetReports extends Command {
 protected $signature='reports:install-permission';
 protected $description='Create scheduled report management permission; grant to existing Super Admin roles';
 public function handle(): int {
  $p=Permission::findOrCreate('scheduled-reports.manage','web');
  foreach(Role::where('guard_name','web')->get() as $role)if(strtolower(trim($role->name))==='super admin')$role->givePermissionTo($p);
  app(PermissionRegistrar::class)->forgetCachedPermissions();
  $this->info('Permission installed. Assign scheduled-reports.manage to other report administrators as needed. No delivery schedules are enabled automatically.');return self::SUCCESS;
 }
}
