<?php

namespace Modules\Gateways\Traits;

trait AddonActivationClass
{
    public function isActive(): array
    {
		return [
			'active' => 1
		];
        if (self::is_local()) {
            return [
                'active' => 1
            ];
        } else {
            $remove = array("http://", "https://", "www.");
            $url = str_replace($remove, "", url('/'));
            $info = include('Modules/Gateways/Addon/info.php');
            $route = route('admin.addon.index');

            if ($info['username'] == '' || $info['purchase_code'] == '') {
                return [
                    'active' => 0,
                    'route' => $route
                ];
            }

            

            if (!$response) {
                $info = include('Modules/Gateways/Addon/info.php');
                $info['is_published'] = 0;
                $info['username'] = '';
                $info['purchase_code'] = '';
                $str = "<?php return " . var_export($info, true) . ";";
                file_put_contents(base_path('Modules/Gateways/Addon/info.php'), $str);
            }

            return [
                'active' => $response,
                'route' => $route
            ];
        }
    }

    public function is_local(): bool
    {
		return true;
        $whitelist = array(
            '127.0.0.1',
            '::1'
        );

        if (!in_array(request()->ip(), $whitelist)) {
            return false;
        }

        return true;
    }
}
