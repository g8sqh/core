<?php

namespace Biigle;

use Illuminate\Database\Eloquent\Model;

/**
 * Pivot object for the connection between Annotations and Labels.
 */
class AnnotationLabel extends Model
{
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'confidence' => 'float',
    ];

    /**
     * The annotation, this annotation label belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function annotation()
    {
        return $this->belongsTo(Annotation::class);
    }

    /**
     * The label, this annotation label belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function label()
    {
        return $this->belongsTo(Label::class);
    }

    /**
     * The user who created this annotation label.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class)
            ->select('id', 'firstname', 'lastname', 'role_id');
    }
}
