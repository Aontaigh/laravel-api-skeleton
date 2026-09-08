<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessions;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\Sessions\AppliesSessionShowParams;
use App\Models\WebSession;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises Web Session show requests.
 */
final class SessionShowRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesSessionShowParams;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * The scoped `{web_session}` binding already 404s rows outside the
     * viewer's scope, so a denied viewer never learns whether the row exists.
     *
     * @return bool true when the User may view the route-bound Web Session
     */
    public function authorize(): bool
    {
        /** @var WebSession|null $webSession */
        $webSession = $this->route('web_session');

        return $webSession instanceof WebSession
            && $this->user()?->can('view', $webSession) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->sessionShowRules();
    }

    /*
    |--------------------------------------------------------------------------
    | Validator Hooks
    |--------------------------------------------------------------------------
    */

    /**
     * Run allow-list validation for the request's query params.
     *
     * @param  Validator $validator the validator under extension
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateSessionShowParams($validator);
    }
}
