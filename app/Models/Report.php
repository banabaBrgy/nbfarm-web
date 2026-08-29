<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
  protected $fillable = [
    'report_no',
    'report_type',
    'manager_id',
    'title',
    'content',
    'owner_comment',
    'is_read',
    'status',
];

    protected $casts = [
        'sent_to_owner' => 'boolean',
        'is_read' => 'boolean',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}