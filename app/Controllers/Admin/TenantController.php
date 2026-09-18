<?php
namespace BBS\App\Controllers\Admin;

use BBS\Core\Controller;
use BBS\Core\Request;
use BBS\Core\Response;
use BBS\App\Models\Tenant;
use BBS\App\Services\LicenseService;

class TenantController extends Controller
{
    private LicenseService $licenseService;

    public function __construct()
    {
        parent::__construct();
        $this->licenseService = new LicenseService();
    }

    public function index(Request $request): void
    {
        $page = (int)($request->input('page', 1));
        $perPage = (int)($request->input('per_page', 15));
        $search = $request->input('search', '');

        $query = Tenant::query();
        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('domain', 'LIKE', "%{$search}%")
                  ->orWhere('slug', 'LIKE', "%{$search}%");
        }

        $total = $query->count();
        $tenants = $query->orderBy('created_at', 'DESC')
                         ->limit($perPage)
                         ->offset(($page - 1) * $perPage)
                         ->get();

        Response::paginated($tenants, $total, $page, $perPage);
    }

    public function create(Request $request): void
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:tenants',
            'domain' => 'string|max:255',
            'plan' => 'required|in:basic,pro,enterprise,lifetime',
            'branding_name' => 'string|max:255',
            'contact_phone' => 'phone',
            'contact_email' => 'email',
        ]);

        $data['uuid'] = generate_uuid();
        $data['status'] = 'trial';

        if (empty($data['branding_name'])) {
            $data['branding_name'] = $data['name'];
        }

        $tenant = Tenant::create($data);

        // Activate trial license
        $trial = $this->licenseService->activateTrial($tenant->id, 14);

        Response::json([
            'success' => true,
            'tenant' => $tenant->toArray(),
            'trial' => $trial,
        ], 201);
    }

    public function show(Request $request, array $params): void
    {
        $tenant = Tenant::find((int)$params['id']);
        if (!$tenant) {
            Response::notFound('Tenant not found');
            return;
        }
        Response::json(['success' => true, 'tenant' => $tenant->toArray()]);
    }

    public function activate(Request $request, array $params): void
    {
        $tenant = Tenant::find((int)$params['id']);
        if (!$tenant) {
            Response::notFound('Tenant not found');
            return;
        }
        $tenant->status = 'active';
        $tenant->save();
        Response::success('Tenant activated', ['tenant' => $tenant->toArray()]);
    }

    public function suspend(Request $request, array $params): void
    {
        $tenant = Tenant::find((int)$params['id']);
        if (!$tenant) {
            Response::notFound('Tenant not found');
            return;
        }
        $tenant->status = 'suspended';
        $tenant->save();
        Response::success('Tenant suspended', ['tenant' => $tenant->toArray()]);
    }

    public function update(Request $request, array $params): void
    {
        $tenant = Tenant::find((int)$params['id']);
        if (!$tenant) {
            Response::notFound('Tenant not found');
            return;
        }

        $allowed = ['name', 'domain', 'plan', 'branding_name', 'branding_logo',
                     'branding_tagline', 'branding_primary_color', 'branding_secondary_color',
                     'contact_phone', 'contact_email', 'contact_address', 'contact_website'];

        foreach ($allowed as $field) {
            if ($request->filled($field)) {
                $tenant->$field = $request->input($field);
            }
        }
        $tenant->save();

        Response::success('Tenant updated', ['tenant' => $tenant->toArray()]);
    }

    public function stats(Request $request): void
    {
        $stats = [
            'total' => Tenant::count(),
            'active' => Tenant::where('status', '=', 'active')->count(),
            'suspended' => Tenant::where('status', '=', 'suspended')->count(),
            'trial' => Tenant::where('status', '=', 'trial')->count(),
            'basic' => Tenant::where('plan', '=', 'basic')->count(),
            'pro' => Tenant::where('plan', '=', 'pro')->count(),
            'enterprise' => Tenant::where('plan', '=', 'enterprise')->count(),
        ];
        Response::json(['success' => true, 'stats' => $stats]);
    }
}
