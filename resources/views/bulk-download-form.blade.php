<!DOCTYPE html>
<html>
<head>
    <title>Downloading...</title>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ $url }}';
            
            // Add CSRF token
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);
            
            // Add IDs
            @foreach($ids as $id)
            const id{{ $id }} = document.createElement('input');
            id{{ $id }}.type = 'hidden';
            id{{ $id }}.name = 'ids[]';
            id{{ $id }}.value = '{{ $id }}';
            form.appendChild(id{{ $id }});
            @endforeach
            
            document.body.appendChild(form);
            form.submit();
        });
    </script>
</head>
<body>
    <p>Downloading QR codes...</p>
</body>
</html>
