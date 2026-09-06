<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Reference - {{ config('app.name') }}</title>
</head>
<body>
    {{-- The attribute-based embed with a pinned Scalar version is deliberate. The
         `Scalar.createApiReference()` path on the unpinned CDN build leaves the
         Introduction section as permanent loading skeletons and an empty sidebar;
         `data-url` + a pinned bundle renders immediately and fully. Re-pin only
         after verifying the intro section hydrates, and refresh the SRI hash
         (`curl -s <bundle-url> | openssl dgst -sha384 -binary | openssl base64 -A`)
         in the same change - the hash pins the exact JavaScript executed on this
         origin, where a visitor's session cookie is JS-readable. --}}
    <div id="api-reference" data-url="{{ url('/api/openapi.yaml') }}" data-configuration='{"persistAuth": true}'></div>

    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference@1.64.1"
        integrity="sha384-SmFRDuBBmEoCbbBCTcn8/+cIQONyH0xgOKpsp6OttBlI8uG9uc6iAO8SonYQ3e1W"
        crossorigin="anonymous"></script>
</body>
</html>
