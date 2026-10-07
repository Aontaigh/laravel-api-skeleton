<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parses a comma-separated list filter value into a typed list.
 *
 * The industry convention for a multi-value filter over a query string is a comma-separated list
 * on the existing key (`filter[user_id]=1,2,3`), not a pluralised sibling key and not a nested
 * operator object. GitHub labels, Shopify tags, Reddit Ads IDs, Zendesk ticket IDs, and Pinterest
 * domains all document this form. A single value is the same list of one, so an existing scalar
 * caller keeps working unchanged.
 *
 * Splitting itself is delegated to `CommaSeparatedList`, which already backs `fields[…]` and
 * `include`. This adds the two things a filter needs that those do not: a published cap, and
 * de-duplication. Exceeding the cap raises rather than silently truncating, because a truncated
 * list would quietly return a partial answer that looks complete.
 *
 * Commas are only safe because these filters are typed scalars (integers or backed enum values)
 * whose values cannot themselves contain a comma. A filter over free text must use a different
 * transport.
 */
final class CommaListParser
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * The largest list any single filter may carry.
     *
     * Sized against the largest vendor caps in the evidence (Reddit Ads allows 200 IDs, Zendesk
     * 100) while staying well inside a URL length budget for integer IDs.
     */
    public const MAX_ITEMS = 100;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Split a raw comma-separated filter value into a list of trimmed, distinct, non-empty parts.
     *
     * De-duplicates while preserving first-seen order so the generated SQL is stable and
     * cacheable, and so `1,1,2` cannot widen the `IN` clause.
     *
     * The cap counts the **non-empty** segments, before de-duplication. Two properties follow, and
     * both matter:
     *
     * - A blank-only value means "no filter", so it must not be able to trip a cap: counting raw
     *   segments made `filter[status]=,,,` a 422 against a cap of 3 while `filter[user_id]=,,,`
     *   was accepted against a cap of 50 - the same semantically empty input answered two
     *   different ways depending on the cap. Counting the values that survive trimming makes the
     *   answer depend on the input rather than on which filter was used.
     * - De-duplication happens after the count, so `1,1,1` is still three values against a cap of
     *   one. Counting the deduped list instead would let repeats smuggle past a cap.
     *
     * Request *size* is deliberately not bounded by a `max` rule on the raw string: the count
     * cap above is the only size bound, and a run of empty commas trims to zero parts, so it
     * can never trip the cap. Raw length stays a transport concern (nginx header limits,
     * `post_max_size`) - see **List Size Is Bounded By The Count Cap** in
     * `ParsesCommaListQueryParam`.
     *
     * @param  string|null  $raw the raw filter value, or null when absent
     * @param  int          $max the largest list permitted for this filter
     * @return list<string> the distinct, trimmed parts, empty when the value is blank
     *
     * @throws ListTooLongException when the value carries more parts than permitted
     */
    public static function split(?string $raw, int $max = self::MAX_ITEMS): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = CommaSeparatedList::parse($raw);

        if (count($parts) > $max) {
            throw new ListTooLongException(count($parts), $max);
        }

        return array_values(array_unique($parts));
    }

    /**
     * Split a raw comma-separated filter value into a list of integers.
     *
     * This trusts its input to be numeric because `CommaListRule` has already checked every part
     * by the time a controller calls this: the FormRequest validates first, then the controller
     * reads the accessor, so a non-numeric part is rejected with a 422 and never arrives. The cast
     * is therefore a cheap conversion, not a fallback - and it must not be used on unvalidated
     * input, where `intval` would quietly turn `abc` into `0` and silently drop the caller onto a
     * wrong result set.
     *
     * @param  string|null $raw the raw filter value, or null when absent
     * @param  int         $max the largest list permitted for this filter
     * @return list<int>   the distinct IDs, empty when the value is blank
     *
     * @throws ListTooLongException when the value carries more parts than permitted
     */
    public static function integers(?string $raw, int $max = self::MAX_ITEMS): array
    {
        return array_map(intval(...), self::split($raw, $max));
    }
}
