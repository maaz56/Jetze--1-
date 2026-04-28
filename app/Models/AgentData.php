<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentData extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'agents_data'; // Explicitly define the table name

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }
}


