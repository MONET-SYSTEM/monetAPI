<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AdminSettingsController extends Controller
{
    /**
     * Show the system settings page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $stats = [
            'table_count' => $this->getTableCount(),
        ];

        return view('admin.settings', compact('stats'));
    }

    /**
     * Update application settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'app_name' => 'required|string|max:255',
            'app_url' => 'required|url',
            'app_timezone' => 'required|string',
            'app_debug' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.settings')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Update .env file
            $this->updateEnvFile([
                'APP_NAME' => $request->app_name,
                'APP_URL' => $request->app_url,
                'APP_TIMEZONE' => $request->app_timezone,
                'APP_DEBUG' => $request->app_debug ? 'true' : 'false',
            ]);

            // Clear config cache
            Artisan::call('config:clear');

            return redirect()->route('admin.settings')
                ->with('success', 'Application settings updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.settings')
                ->with('error', 'Error updating settings: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Update mail settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateMail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mail_driver' => 'required|string',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|integer',
            'mail_from_address' => 'nullable|email',
            'mail_from_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.settings')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Update .env file
            $this->updateEnvFile([
                'MAIL_MAILER' => $request->mail_driver,
                'MAIL_HOST' => $request->mail_host,
                'MAIL_PORT' => $request->mail_port,
                'MAIL_FROM_ADDRESS' => $request->mail_from_address,
                'MAIL_FROM_NAME' => '"' . $request->mail_from_name . '"',
            ]);

            // Clear config cache
            Artisan::call('config:clear');

            return redirect()->route('admin.settings')
                ->with('success', 'Mail settings updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.settings')
                ->with('error', 'Error updating mail settings: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Test email configuration.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testEmail()
    {
        try {
            $admin = auth()->guard('admin')->user();
            
            Mail::raw('This is a test email from Monet Admin Panel.', function ($message) use ($admin) {
                $message->to($admin->email)
                        ->subject('Test Email - Monet Admin Panel');
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Test email sent successfully to ' . $admin->email
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test database connection.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testDatabase()
    {
        try {
            DB::connection()->getPdo();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Database connection successful'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear application cache.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache()
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            return response()->json([
                'status' => 'success',
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update .env file with new values.
     *
     * @param array $data
     * @return void
     */
    private function updateEnvFile(array $data)
    {
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);

        foreach ($data as $key => $value) {
            $envContent = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $envContent
            );
            
            // If the key doesn't exist, add it
            if (!preg_match("/^{$key}=/m", $envContent)) {
                $envContent .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envFile, $envContent);
    }

    /**
     * Get the count of database tables.
     *
     * @return int
     */
    private function getTableCount()
    {
        try {
            $tables = DB::select('SHOW TABLES');
            return count($tables);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
