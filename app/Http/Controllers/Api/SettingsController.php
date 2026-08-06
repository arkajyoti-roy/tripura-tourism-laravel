<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use App\Mail\OtpMail;

class SettingsController extends Controller
{
    public function getDashboardStats()
    {
        $photos = DB::table('photo')->count();
        $tours = DB::table('tour')->count();
        $accommodations = DB::table('accommodation')->count();
        $guides = DB::table('guides')->count();
        $adventures = DB::table('adventures')->count();
        $agents = DB::table('agents')->count();
        $documents = DB::table('documents')->count();
        $testimonials = DB::table('testimonials')->count();

        $visitors = DB::table('visitor_counter')->where('id', 1)->value('count') ?? 11225460;
        $today_visitors = DB::table('visitor')->whereDate('created_at', today())->count();

        
        $recent_logs = DB::table('activity_logs')
            ->leftJoin('admin', 'activity_logs.admin_id', '=', 'admin.id')
            ->select(
                'activity_logs.id',
                'activity_logs.action_type',
                'activity_logs.section',
                'activity_logs.resource',
                'activity_logs.details',
                'activity_logs.ip_address',
                'activity_logs.created_at',
                'admin.name as admin_name',
                DB::raw('COALESCE(activity_logs.operator_email, admin.email) as admin_email')
            )
            ->orderBy('activity_logs.id', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'photos' => $photos,
            'tours' => $tours,
            'accommodations' => $accommodations,
            'guides' => $guides,
            'adventures' => $adventures,
            'agents' => $agents,
            'documents' => $documents,
            'testimonials' => $testimonials,
            'visitors' => (int)$visitors,
            'today_visitors' => (int)$today_visitors,
            'recent_logs' => $recent_logs
        ]);
    }

    

    

    private function applySmtpFromDb(): void
    {
        $smtp = DB::table('smtp_settings')->first();
        if ($smtp && $smtp->mail_host) {
            Config::set('mail.mailers.smtp.host',       $smtp->mail_host);
            Config::set('mail.mailers.smtp.port',       $smtp->mail_port);
            Config::set('mail.mailers.smtp.username',   $smtp->mail_username);
            Config::set('mail.mailers.smtp.password',   $smtp->mail_password);
            Config::set('mail.mailers.smtp.encryption', $smtp->mail_encryption);
            Config::set('mail.from.address',            $smtp->mail_from_address ?? $smtp->mail_username);
            Config::set('mail.from.name',               $smtp->mail_from_name ?? config('app.name'));
            Config::set('mail.default',                 $smtp->mail_mailer ?? 'smtp');
        }
    }

    

    

    public function getSmtpSettings()
    {
        $smtp = DB::table('smtp_settings')->first();
        if ($smtp) {
            $smtp->mail_password = ''; 
        }
        return response()->json($smtp);
    }

    

    public function saveSmtpSettings(Request $request)
    {
        $request->validate([
            'mail_host'         => 'required|string',
            'mail_port'         => 'required|integer',
            'mail_username'     => 'required|email',
            'mail_password'     => 'nullable|string',
            'mail_encryption'   => 'nullable|string|in:ssl,tls,null',
            'mail_from_address' => 'nullable|email',
            'mail_from_name'    => 'nullable|string',
            'mail_mailer'       => 'nullable|string',
        ]);

        $data = [
            'mail_mailer'       => $request->mail_mailer ?? 'smtp',
            'mail_host'         => $request->mail_host,
            'mail_port'         => $request->mail_port,
            'mail_username'     => $request->mail_username,
            'mail_encryption'   => $request->mail_encryption,
            'mail_from_address' => $request->mail_from_address ?? $request->mail_username,
            'mail_from_name'    => $request->mail_from_name ?? config('app.name'),
            'updated_at'        => now(),
        ];

        
        if ($request->filled('mail_password')) {
            $data['mail_password'] = $request->mail_password;
        }

        $existing = DB::table('smtp_settings')->first();
        if ($existing) {
            DB::table('smtp_settings')->where('id', $existing->id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('smtp_settings')->insert($data);
        }

        return response()->json(['message' => 'SMTP settings saved successfully']);
    }

    

    public function testSmtp(Request $request)
    {
        $request->validate(['to' => 'required|email']);
        $this->applySmtpFromDb();
        try {
            Mail::raw('This is a test email from Tripura Tourism admin portal.', function ($msg) use ($request) {
                $msg->to($request->to)->subject('SMTP Test – Tripura Tourism');
            });
            return response()->json(['message' => 'Test email sent successfully to ' . $request->to]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    

    

    public function sendOtp(Request $request)
    {
        $admin = $request->user();
        $otp   = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('admin_otps')->updateOrInsert(
            ['email' => $admin->email],
            ['otp' => $otp, 'expires_at' => now()->addMinutes(10), 'created_at' => now()]
        );

        $this->applySmtpFromDb();

        try {
            Mail::to($admin->email)->send(new OtpMail($otp));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send OTP: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'OTP sent to ' . $admin->email]);
    }

    

    public function changePassword(Request $request)
    {
        $request->validate([
            'otp'          => 'required|digits:6',
            'new_password' => 'required|string|min:6',
        ]);

        $admin  = $request->user();
        $record = DB::table('admin_otps')
            ->where('email', $admin->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>=', now())
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 401);
        }

        DB::table('admin_otps')->where('email', $admin->email)->delete();
        $admin->password = md5($request->new_password);
        $admin->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    

    public function changeEmail(Request $request)
    {
        $request->validate([
            'otp'       => 'required|digits:6',
            'new_email' => 'required|email',
        ]);

        $admin  = $request->user();
        $record = DB::table('admin_otps')
            ->where('email', $admin->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>=', now())
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 401);
        }

        DB::table('admin_otps')->where('email', $admin->email)->delete();
        $admin->email = $request->new_email;
        $admin->save();

        return response()->json(['message' => 'Email changed successfully.', 'new_email' => $admin->email]);
    }

    

    public function getAccessEmails()
    {
        
        $primaryAdmin = \App\Models\Admin::first();
        $primaryEmail = $primaryAdmin ? $primaryAdmin->email : null;

        
        $emails = DB::table('access_emails')->orderBy('id', 'desc')->get()->toArray();

        
        $result = [];
        if ($primaryEmail) {
            $result[] = [
                'id'         => 'primary',
                'email'      => $primaryEmail,
                'is_primary' => true,
                'created_at' => $primaryAdmin->created_at ?? now(),
                'updated_at' => $primaryAdmin->updated_at ?? now(),
            ];
        }

        foreach ($emails as $email) {
            
            if ($primaryEmail && ((array)$email)['email'] === $primaryEmail) {
                continue;
            }
            $result[] = (array) $email + ['is_primary' => false];
        }

        return response()->json([
            'status' => 'success',
            'data'   => $result
        ]);
    }

    public function addAccessEmail(Request $request)
    {
        $request->validate(['email' => 'required|email|unique:access_emails,email']);
        DB::table('access_emails')->insert(['email' => $request->email, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Access email added successfully.'], 201);
    }

    public function deleteAccessEmail(string $id)
    {
        
        if ($id === 'primary') {
            return response()->json(['message' => 'The primary administrator email cannot be revoked.'], 403);
        }

        
        $primaryAdmin = \App\Models\Admin::first();
        $entry = DB::table('access_emails')->where('id', $id)->first();
        if ($entry && $primaryAdmin && $entry->email === $primaryAdmin->email) {
            return response()->json(['message' => 'The primary administrator email cannot be revoked.'], 403);
        }

        DB::table('access_emails')->where('id', $id)->delete();
        return response()->json(['message' => 'Access email deleted successfully.']);
    }
}
