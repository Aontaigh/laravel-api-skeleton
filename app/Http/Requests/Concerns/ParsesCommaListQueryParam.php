<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\CommaListParser;
use App\Support\CommaListRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared rules and accessors for a filter that accepts one value or a comma-separated list.
 *
 * A multi-value filter keeps its singular key and accepts a comma-separated list:
 * `filter[user_id]=1` and `filter[user_id]=1,2,3` are the same filter, so an
 * existing scalar caller needs no change. This is the form GitHub labels,
 * Shopify tags, Reddit Ads IDs, Zendesk ticket IDs, and Pinterest domains all
 * document. A pluralised sibling key (`filter[user_ids]`) and a nested operator
 * object (`filter[user_id][any_of]`) were both considered and rejected: the
 * former makes clients guess which key is canonical, the latter has no precedent
 * in any surveyed production API.
 *
 * The rule stays `string` rather than `integer` because a list is not an
 * integer. Validating the parts themselves is what keeps `filter[user_id]=1,x`
 * a 422 instead of a silently widened result set.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait ParsesCommaListQueryParam
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ReadsRequestInput;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Build the validation rules for a list-capable integer ID filter.
     *
     * The key stays `string` because a list is not an integer, so the `integer` check is applied
     * to each part by `CommaListRule`. Without the per-part rule `1,abc` would pass, because the
     * whole string satisfies the outer `string` rule.
     *
     * The per-part rule stays a bare `integer` with no `min:1`: a non-positive ID is a valid
     * shape that honestly matches no rows, and rejecting it would turn a `200` into a `422`
     * for callers already sending one - a published-contract change, not a tidy-up.
     *
     * No `max` on the raw string: see **List Size Is Bounded By The Count Cap** below.
     *
     * @param  string                           $key the `filter[…]` key, for example `filter.user_id`
     * @param  int                              $max the largest list permitted for this filter
     * @return array<string, array<int, mixed>>
     */
    protected function commaListFilterRules(string $key, int $max = CommaListParser::MAX_ITEMS): array
    {
        return [
            $key => [
                'sometimes',
                'nullable',
                'string',
                new CommaListRule($max, 'integer'),
            ],
        ];
    }

    /*
     * List Size Is Bounded By The Count Cap
     *
     * There is deliberately no `max` on the raw filter string, on any list filter, integer or enum
     * alike. The count cap in `CommaListRule` is the only size bound, and that matches what the
     * large providers do.
     *
     * GitHub, Stripe, Meta, PayPal, and Atlassian all cap the *count* of values in a list param,
     * the *depth* of a nested one, or the page size. None publishes a raw-length bound on a
     * comma-separated list. The closest published figure, GitHub's 256-character limit, is on
     * free-text search rather than a list.
     *
     * The reasoning:
     *
     * - The count cap is what bounds the work: array size, `IN` clause length, and the query plan.
     *   Those are the costs that actually matter.
     * - Request size is already a transport concern. nginx caps request headers and `post_max_size`
     *   is 100M, so a padded value is rejected long before parsing it would hurt. Measured on a
     *   running instance, twenty thousand commas followed by a valid value answers `200` in 0.038s,
     *   which is not a slow path and not a denial of service.
     * - A raw-length cap is a contract liability. The budget must exceed the longest legal value, so
     *   it silently tracks the value vocabulary: renaming `Client Token Exchange Failed` to
     *   something longer would start rejecting requests that were valid yesterday. Capping the count
     *   instead keeps the contract stable when values change.
     *
     * If a request-size bound is ever genuinely needed, it belongs at the server - a
     * `large_client_header_buffers` limit in the nginx config applies uniformly to every request -
     * rather than in per-filter validation that would have to be re-derived whenever an enum gains a
     * longer case.
     */

    /**
     * Read a list-capable filter as a list of integer IDs.
     *
     * Returns an empty list when the filter is absent, so callers branch on
     * emptiness alone and never on null.
     *
     * @param  string    $key the `filter[…]` key, for example `filter.user_id`
     * @param  int       $max the largest list permitted for this filter
     * @return list<int>
     */
    public function integerList(string $key, int $max = CommaListParser::MAX_ITEMS): array
    {
        return CommaListParser::integers($this->safe()->string($key)->toString(), $max);
    }

    /**
     * Read a list-capable filter as a list of raw strings.
     *
     * @param  string       $key the `filter[…]` key, for example `filter.status`
     * @param  int          $max the largest list permitted for this filter
     * @return list<string>
     */
    public function stringList(string $key, int $max = CommaListParser::MAX_ITEMS): array
    {
        return CommaListParser::split($this->safe()->string($key)->toString(), $max);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    /**
     * Message copy for a list-capable filter key, keyed by rule.
     *
     * A dotted attribute humanises badly - a rule on `filter.user_id` otherwise answers
     * `The filter.user id field must be a string.`, which is sentence case, carries a trailing
     * period, and names a key the client never sent. Each key therefore gets its own entry, and
     * the attribute is named literally with its prefix intact.
     *
     * @return array<string, string>
     */
    public function commaListFilterMessages(): array
    {
        $messages = [];

        foreach (array_keys($this->commaListFilterDefinitions()) as $key) {
            $messages["{$key}.string"] = sprintf('The %s Value Must Be A Comma-Separated List', $key);
        }

        return $messages;
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-List Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Attach the allow-list hint whenever a declared filter is rejected.
     *
     * `validateFilterKeys` records the hint for an unknown key, which covers a typo but not a
     * value outside the allow-list: a `422` naming one bad value would otherwise leave the caller
     * with no list of what was accepted, and the rule requires every rejected whitelisted query
     * param to help the caller self-correct without reading OpenAPI. One hint serves every
     * rejected key, because the supported set is the same for all of them.
     *
     * @param  Validator $validator the validator under extension
     * @return void
     */
    public function validateCommaListFilterHints(Validator $validator): void
    {
        $validator->after(function (Validator $check): void {
            foreach (array_keys($this->commaListFilterDefinitions()) as $key) {
                if (! $check->errors()->has($key)) {
                    continue;
                }

                $this->recordAllowListHint('filter', $this->allowedFilterKeys());

                return;
            }
        });
    }

    /**
     * The list-capable filters on this request, mapped to their maximum list size.
     *
     * Each resource declares its own so the cap can differ per filter: a `filter[role]` enum is
     * naturally short, while an ID list may be longer. The map is read at runtime by
     * `commaListFilterMessages()` and `validateCommaListFilterHints()`, so every declared filter
     * gets its literal message copy and its allow-list hint from the one declaration. It never
     * re-enforces the cap itself - that stays in `CommaListRule` alone, one place to get wrong.
     *
     * @return array<string, int>
     */
    protected function commaListFilterDefinitions(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

    /**
     * Get the `filter[…]` keys this resource accepts.
     *
     * Declared abstractly, like the accessors in `ReadsRequestInput`, so a host
     * that forgets it is a compile-time fatal rather than a runtime surprise.
     *
     * @return list<string> the allowed filter keys
     */
    abstract protected function allowedFilterKeys(): array;
}
