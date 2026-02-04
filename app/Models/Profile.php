<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Profile extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['email', 'password', 'is_pro', 'referral_code', 'free_reports_used'];
    protected $hidden = ['password'];

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}
