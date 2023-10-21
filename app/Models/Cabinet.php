<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Cabinet extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The attributes that are logged.
     *
     * @var array<int, string>
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'logo', 'year', 'is_active', 'visi', 'misi'])
            ->logOnlyDirty()
            ->useLogName('Cabinet');
    }

    protected $fillable = [
        'name',
        'description',
        'logo',
        'year',
        'is_active',
        'visi',
        'misi',        
    ];

    public function filosofies()
    {
        return $this->hasMany(Filosofie::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'periodes', 'cabinet_id', 'user_id')->withPivot('id', 'is_active', 'position')->withTimestamps();
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'periodes', 'cabinet_id', 'department_id')->withPivot('id', 'is_active', 'position')->withTimestamps();
    }

    public function periodes()
    {
        return $this->hasMany(Periode::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
