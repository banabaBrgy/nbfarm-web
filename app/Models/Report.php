<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Report extends Model
{
    protected $fillable = [
        'report_no',
        'report_type',
        'manager_id',
        'title',
        'content',
        'report_date',
        'owner_comment',
        'is_read',
        'status',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'sent_to_owner' => 'boolean',
        'is_read' => 'boolean',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'report_date' => 'date',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** Keep only columns that exist on this database. */
    public static function existing(array $attrs): array
    {
        $table = (new static)->getTable();

        return collect($attrs)
            ->filter(fn ($value, $key) => Schema::hasColumn($table, $key))
            ->all();
    }
}
