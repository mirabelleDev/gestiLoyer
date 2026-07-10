namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'profile_type',
        'profile_id',
        'pin_code',
        'is_active',
        'last_login_at'
    ];

    protected $hidden = [
        'password',
        'pin_code',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->morphTo();
    }

    public function configuration()
    {
        return $this->hasOne(Configuration::class);
    }

    public function paiementsValides()
    {
        return $this->hasMany(Paiement::class, 'valide_par');
    }
}