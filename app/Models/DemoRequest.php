<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A message from the landing page contact form: a demo, a custom plan, or anything else. Platform-level: it belongs to no organization.
 *
 * @property int $id
 * @property string $subject
 * @property string $name
 * @property string $organization
 * @property string $email
 * @property string|null $phone
 * @property string|null $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DemoRequest extends Model
{
    /** @var array<string, string> subject => what the visitor picked, also the email's subject prefix */
    public const SUBJECTS = [
        'demo' => 'Demande de démo',
        'custom' => 'Formule sur mesure',
        'other' => 'Autre demande',
    ];

    protected $fillable = ['subject', 'name', 'organization', 'email', 'phone', 'message'];

    /**
     * The subject in words.
     */
    public function subjectLabel(): string
    {
        return self::SUBJECTS[$this->subject] ?? self::SUBJECTS['other'];
    }
}
