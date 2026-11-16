<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected static function booted(): void
    {
        static::created(function (self $user): void {
            if ($user->role !== 'customer') {
                return;
            }

            // Run only once the customer record is committed. This covers the
            // public registration flow and any other future customer-creation
            // endpoint without broadcasting a rolled-back registration.
            DB::afterCommit(function () use ($user): void {
                self::query()
                    ->where('role', 'admin')
                    ->each(fn (self $admin) => $admin->notify(
                        new \App\Notifications\NewUserRegistered($user)
                    ));
            });
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function customer(){
        return $this->hasOne(Customer::class, 'user_id' );
    }
    public function agentData()
    {
        return $this->hasOne(AgentData::class, 'agent_id' );
    }

    public function staffMeta(){
        return $this->hasMany(AgentData::class, 'user_id');
    }

    public function notifications()
    {
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable');
    }

    /**
     * Keep the private Pusher channel name stable and independent of the
     * model's PHP namespace.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'users.' . $this->getKey();
    }

    public function offlineBookings()
    {
        return $this->hasMany(OfflineBooking::class, 'agent_id');
    }
    
}
