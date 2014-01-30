<?php
/**
 * Api_Admin should be used for building your own application's administration
 * model. The benefit is that you'll have access to a number of add-ons which
 * are specifically written for admin system.
 *
 * Exporting add-ons, database migration, test-suites and other add-ons
 * have developed User Interface which can be simply "attached" to your
 * application's admin.
 *
 * This is done through hooks in the Admin Class. It's also important that
 * Api_Admin relies on layout_fluid which makes it easier for add-ons to
 * add menu items, sidebars and foot-bars.
 */
class Api_Admin extends ApiFrontend {

    public $title='Agile Toolkit™ Admin';

    function init() {
        parent::init();


        $this->add('Layout_Fluid');

        $this->menu = $this->layout->addMenu();
        $this->layout->addFooter();

        $this->add('jUI');

        $this->addSandbox();
    }

    private function addSandbox() {
        if ($this->pathfinder->sandbox) {
            $sandbox = $this->api->add('sandbox\\Initiator');
            if ($sandbox->getGuardError()) {
                $this->sandbox->police->addErrorView($this->layout);
            }
        }
    }

    function initLayout() {
        if ($this->pathfinder->sandbox) {
            $this->addAddonsLocations();
            $this->initAddons();
        }
        parent::initLayout();
    }


    function addAddonsLocations() {
        $a = $this->add('sandbox\\Controller_InstallAddon');
        $base_path = $this->pathfinder->base_location->getPath();
        foreach ($a->getSndBoxAddonReader()->getReflections() as $addon) {
            // Private location contains templates and php files YOU develop yourself
            /*$this->private_location = */
            $this->api->pathfinder->addLocation(array(
                'docs'      => 'docs',
                'php'       => 'lib',
                'addons'    => '../..',
                'page'      => 'page',
                'template'  => 'templates',
            ))
                    ->setBasePath($base_path.'/../'.$addon->get('addon_full_path'))
            ;

            $addon_public = $addon->get('addon_symlink_name');
            // this public location cotains YOUR js, css and images, but not templates
            /*$this->public_location = */
            $this->api->pathfinder->addLocation(array(
                'js'     => 'js',
                'css'    => 'css',
                'public' => './',
                //'public'=>'.',  // use with < ?public? > tag in your template
            ))
                    ->setBasePath($base_path.'/'.$addon->get('addon_public_symlink'))
                    ->setBaseURL($this->api->url('/').$addon_public) // $this->api->pm->base_path
            ;
        }
    }
    function initAddons() {
        $base_path = $this->pathfinder->base_location->getPath();
        $file = $base_path.'/sandbox_addons.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $objects = json_decode($json);
            foreach ($objects as $obj) {
                // init addon
                $init_class_path = $base_path.'/'.$obj->addon_full_path.'/lib/Initiator.php';
                if (file_exists($init_class_path)) {
                    $class_name = str_replace('/','\\',$obj->name.'\\Initiator');
                    $init = $this->add($class_name,array(
                        'addon_obj' => $obj,
                    ));
                }
            }
        }
    }
}
