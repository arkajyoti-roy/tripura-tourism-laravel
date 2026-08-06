<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        if ($request->has('otp')) {
            return $this->loginWithOtp($request);
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $username = $request->input('email');
        $password = md5($request->input('password'));



        

        // Enforce that secondary emails (in access_emails but not in admins) cannot login via password flow (uncomment to restrict)

        //Comment This code block to allow secondary emails to login with password.

        $isSecondary = DB::table('access_emails')->where('email', $username)->exists() && !\App\Models\Admin::where('email', $username)->exists();
        if ($isSecondary) {
            return response()->json(['status' => 'error', 'message' => 'Secondary operators must login via OTP verification.'], 401);
        }





        $admin = \App\Models\Admin::where('email', $username)->where('password', $password)->first();

        // If not found directly in admins table, check if it exists in access_emails and matches primary admin password
        if (!$admin && DB::table('access_emails')->where('email', $username)->exists()) {
            $primaryAdmin = \App\Models\Admin::first();
            if ($primaryAdmin && $primaryAdmin->password === $password) {
                $admin = $primaryAdmin;
            }
        }

        





        if ($admin) {
            $usertoken = $admin->createToken('admin-token')->plainTextToken;
            
            $existing = DB::table('user_token')->where('userid', $admin->id)->first();
            if ($existing) {
                DB::table('user_token')->where('userid', $admin->id)->update([
                    'token' => $usertoken,
                    'updatedate' => time()
                ]);
            } else {
                DB::table('user_token')->insert([
                    'userid' => $admin->id,
                    'token' => $usertoken,
                    'date' => time()
                ]);
            }

            
            DB::table('activity_logs')->insert([
                'admin_id' => $admin->id,
                'operator_email' => $username,
                'action_type' => 'login',
                'section' => 'system',
                'resource' => 'api/admin/login',
                'details' => 'method: password | status: success',
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'status' => 'success',
                'token' => $usertoken,
                'user' => [
                    'id' => $admin->id,
                    'email' => $username
                ]
            ]);
        }

        return response()->json(['status' => 'error', 'message' => 'Invalid username or password'], 401);
    }

    

    public function sendLoginOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $admin = \App\Models\Admin::where('email', $request->email)->first();
        $isAllowedAccess = DB::table('access_emails')->where('email', $request->email)->exists();

        if (!$admin && !$isAllowedAccess) {
            return response()->json(['message' => 'No authorized admin account found with that email address.'], 404);
        }

        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        
        DB::table('admin_otps')->updateOrInsert(
            ['email' => $request->email],
            ['otp' => $otp, 'expires_at' => $expiresAt, 'created_at' => now()]
        );

        try {
            
            $smtp = \Illuminate\Support\Facades\DB::table('smtp_settings')->first();
            if ($smtp && $smtp->mail_host) {
                \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.host',       $smtp->mail_host);
                \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.port',       $smtp->mail_port);
                \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.username',   $smtp->mail_username);
                \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.password',   $smtp->mail_password);
                \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.encryption', $smtp->mail_encryption);
                \Illuminate\Support\Facades\Config::set('mail.from.address',            $smtp->mail_from_address ?? $smtp->mail_username);
                \Illuminate\Support\Facades\Config::set('mail.from.name',               $smtp->mail_from_name ?? config('app.name'));
                \Illuminate\Support\Facades\Config::set('mail.default',                 $smtp->mail_mailer ?? 'smtp');
            }
            Mail::to($request->email)->send(new OtpMail($otp));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send OTP. Please configure SMTP settings in admin portal. Error: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'OTP verification code has been sent to your email.']);
    }

    

    public function loginWithOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|digits:6',
        ]);

        $record = DB::table('admin_otps')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>=', now())
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 401);
        }

        $admin = \App\Models\Admin::where('email', $request->email)->first();

        if (!$admin) {
            
            $isAllowedAccess = DB::table('access_emails')->where('email', $request->email)->exists();
            if ($isAllowedAccess) {
                
                $admin = \App\Models\Admin::first();
            }
        }

        if (!$admin) {
            return response()->json(['message' => 'Admin account not found.'], 404);
        }

        DB::table('admin_otps')->where('email', $request->email)->delete();

        $usertoken = $admin->createToken('admin-token')->plainTextToken;

        
        DB::table('activity_logs')->insert([
            'admin_id' => $admin->id,
            'operator_email' => $request->email,
            'action_type' => 'login',
            'section' => 'system',
            'resource' => 'api/admin/login-otp',
            'details' => 'method: otp | status: success',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'token'  => $usertoken,
            'user'   => ['id' => $admin->id, 'email' => $request->email],
        ]);
    }

    

    public function changeCredentials(Request $request)
    {
        $request->validate([
            'email'            => 'required|email',
            'current_password' => 'required',
            'otp'              => 'required|digits:6',
        ]);

        $admin = \App\Models\Admin::where('email', $request->email)
            ->where('password', md5($request->current_password))
            ->first();

        if (!$admin) {
            return response()->json(['message' => 'Current credentials are incorrect.'], 401);
        }

        $record = DB::table('admin_otps')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>=', now())
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 401);
        }

        DB::table('admin_otps')->where('email', $request->email)->delete();

        if ($request->filled('new_password')) {
            $admin->password = md5($request->new_password);
        }

        $admin->save();

        return response()->json(['message' => 'Credentials updated successfully.', 'user' => ['id' => $admin->id, 'email' => $admin->email]]);
    }

    

    public function logout(Request $request)
    {
        $user = $request->user();
        $operatorEmail = $request->header('X-Operator-Email') ?: $user?->email;

        
        DB::table('activity_logs')->insert([
            'admin_id'       => $user?->id,
            'operator_email' => $operatorEmail,
            'action_type'    => 'logout',
            'section'        => 'system',
            'resource'       => 'api/admin/logout',
            'details'        => 'status: success',
            'ip_address'     => $request->ip(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $user?->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}

