<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        /* RESET STYLES */
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        
        /* RESPONSIVE STYLES */
        @media screen and (max-width: 600px) {
            .container { width: 100% !important; }
            .content-padding { padding: 20px !important; }
            .button { display: block !important; width: 100% !important; text-align: center; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">

    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                
                <table border="0" cellpadding="0" cellspacing="0" width="600" class="container" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                    
                    <tr>
                        <td align="center" style="background-color: #eff6ff; padding: 30px;">
                            <img src="https://cdn-icons-png.flaticon.com/512/8552/8552803.png" alt="Pocket Money" width="48" style="display: block;">
                        </td>
                    </tr>

                    <tr>
                        <td class="content-padding" style="padding: 40px;">
                            
                            <h2 style="margin: 0 0 20px 0; color: #1f2937; font-size: 24px; font-weight: 700; text-align: center;">
                                Reset Password Request
                            </h2>

                            <p style="margin: 0 0 24px 0; color: #4b5563; font-size: 16px; line-height: 1.6; text-align: center;">
                                Hello,
                                <br><br>
                                We received a request to reset the password for your <strong>Pocket Money</strong> account. 
                                Click the button below to choose a new password.
                            </p>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 10px 0 30px 0;">
                                        <a href="{{ $resetUrl }}" class="button" style="background-color: #2563eb; color: #ffffff; display: inline-block; font-size: 16px; font-weight: bold; line-height: 50px; text-decoration: none; padding: 0 30px; border-radius: 8px; min-width: 160px; text-align: center;">
                                            Reset Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; color: #6b7280; font-size: 14px; text-align: center;">
                                For your security, this link will expire in <strong>60 minutes</strong>.
                            </p>
                            
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 30px 0;">
                                <tr>
                                    <td style="border-top: 1px solid #e5e7eb;"></td>
                                </tr>
                            </table>

                            <p style="margin: 0; color: #9ca3af; font-size: 13px; text-align: center;">
                                If you did not request a password reset, please ignore this email. 
                                No changes have been made to your account.
                            </p>

                        </td>
                    </tr>
                    
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px; text-align: center;">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                &copy; {{ date('Y') }} Pocket Money. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
                <table border="0" cellpadding="0" cellspacing="0" width="600" class="container">
                    <tr>
                        <td align="center" style="padding-top: 20px; color: #9ca3af; font-size: 12px;">
                            Trouble clicking the button? Copy and paste this URL:<br>
                            <a href="{{ $resetUrl }}" style="color: #2563eb; word-break: break-all;">{{ $resetUrl }}</a>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>
</html>