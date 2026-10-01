<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Registration Successful</title>
</head>

<body>

    <h2>Welcome, {{ $data['first_name'] }}!</h2>

    <p>
        Your registration has been successfully completed.
    </p>

    <p>
        <strong>Account Details</strong>
    </p>

    <p>
        Name:
        {{ $data['first_name'] }}
        {{ $data['last_name'] }}
    </p>

    <p>
        Email:
        {{ $data['email'] }}
    </p>

    <p>
        Account Type:
        {{ $data['account_type'] }}
    </p>

    <p>
        Client ID:
        {{ $data['client_id'] }}
    </p>

    <p>
        Thank you for registering with us.
    </p>

</body>
</html>

