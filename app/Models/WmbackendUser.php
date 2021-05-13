<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class WmbackendUser extends Authenticatable
{
    const STATUS_ACTIVE = '1';
    const STATUS_INACTIVE = '0';
    use Notifiable;
    protected $guard = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'firstname', 'lastname', 'email', 'password', 'role_id', 'lastpwdupdated', 'department_name', 'employee_id','created_by', 'is_franchise_admin','status','special_permission','is_local_shop'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "wmbackend_users";

}
