<?php namespace App\Models;

use Session;
use Auth;
use Event;
use App\Libraries\Utils;
use App\Events\UserSettingsChanged;
use App\Events\UserSignedUp;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model implements AuthenticatableContract, CanResetPasswordContract {

    use Authenticatable, CanResetPassword;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'email', 'password'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token', 'confirmation_code'];

    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function theme()
    {
        return $this->belongsTo('App\Models\Theme');
    }

    public function getName()
    {
        return $this->getDisplayName();
    }

    public function getPersonType()
    {
        return PERSON_USER;
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->password;
    }

    /**
     * Get the e-mail address where password reminders are sent.
     *
     * @return string
     */
    public function getReminderEmail()
    {
        return $this->email;
    }

    public function isPro()
    {
        return $this->account->isPro();
    }

    public function isDemo()
    {
        return $this->account->id == Utils::getDemoAccountId();
    }

    public function maxInvoiceDesignId()
    {
        return $this->isPro() ? 11 : (Utils::isNinja() ? COUNT_FREE_DESIGNS : COUNT_FREE_DESIGNS_SELF_HOST);
    }

    public function getDisplayName()
    {
        if ($this->getFullName()) {
            return $this->getFullName();
        } elseif ($this->email) {
            return $this->email;
        } else {
            return 'Guest';
        }
    }

    public function getFullName()
    {
        if ($this->first_name || $this->last_name) {
            return $this->first_name.' '.$this->last_name;
        } else {
            return '';
        }
    }

    public function showGreyBackground()
    {
        return !$this->theme_id || in_array($this->theme_id, [2, 3, 5, 6, 7, 8, 10, 11, 12]);
    }

    public function getRequestsCount()
    {
        return Session::get(SESSION_COUNTER, 0);
    }

    /*
    public function getPopOverText()
    {
        if (!Utils::isNinja() || !Auth::check() || Session::has('error')) {
            return false;
        }

        $count = self::getRequestsCount();

        if ($count == 1 || $count % 5 == 0) {
            if (!Utils::isRegistered()) {
                return trans('texts.sign_up_to_save');
            } elseif (!Auth::user()->account->name) {
                return trans('texts.set_name');
            }
        }

        return false;
    }
    */
    
    public function afterSave($success = true, $forced = false)
    {
        if ($this->email) {
            return parent::afterSave($success = true, $forced = false);
        } else {
            return true;
        }
    }

    public function getMaxNumClients()
    {
        return $this->isPro() ? MAX_NUM_CLIENTS_PRO : MAX_NUM_CLIENTS;
    }

    public function getRememberToken()
    {
        return $this->remember_token;
    }

    public function setRememberToken($value)
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    public function clearSession()
    {
        $keys = [
            RECENTLY_VIEWED,
            SESSION_USER_ACCOUNTS,
            SESSION_TIMEZONE,
            SESSION_DATE_FORMAT,
            SESSION_DATE_PICKER_FORMAT,
            SESSION_DATETIME_FORMAT,
            SESSION_CURRENCY,
            SESSION_LOCALE,
        ];

        foreach ($keys as $key) {
            Session::forget($key);
        }
    }

    public static function onUpdatingUser($user)
    {
        if ($user->password != $user->getOriginal('password')) {
            $user->failed_logins = 0;
        }
    }

    public static function onUpdatedUser($user)
    {
        if (!$user->getOriginal('email')
            || $user->getOriginal('email') == TEST_USERNAME
            || $user->getOriginal('username') == TEST_USERNAME) {
            event(new UserSignedUp());
        }

        event(new UserSettingsChanged());
    }

}

User::updating(function ($user) {
    User::onUpdatingUser($user);
});

User::updated(function ($user) {
    User::onUpdatedUser($user);
});