<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Reference — {{ config('app.name') }}</title>
</head>
<body>
    <div id="api-reference"></div>

    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
    <script>
        Scalar.createApiReference('#api-reference', {
            url: '{{ url('/api/openapi.yaml') }}',
            persistAuth: true,
        });
    </script>
</body>
</html>
