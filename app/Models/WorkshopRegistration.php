<?php

namespace App\Models;

use Database\Factories\WorkshopRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's seat in a workshop: one registration is one seat (the workshop's "seats" caps them).
 */
#[Fillable(['workshop_id', 'student_id'])]
class WorkshopRegistration extends Model
{
    /** @use HasFactory<WorkshopRegistrationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Workshop, $this>
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
