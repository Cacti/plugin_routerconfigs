<?php
/* Translation library */
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Translation.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Translation/Autodetect.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Translation/Handler.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Translation/Exception.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Translation/Handler/Gettext.php');

/* Exception library */
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Exception.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Exception/Pear.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Exception/Translation.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Exception/PermissionDenied.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Exception/LastError.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Exception/NotFound.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Exception/Wrapped.php');

/* Util Library */
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Util/String.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Util/Domhtml.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Util/Array.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Util/Util.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Util/Variables.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Util/Array/Sort/Helper.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Util/String/Transliterate.php');

/* Diff library */
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Engine/xdiff.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Engine/string.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Engine/native.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Engine/shell.php');

include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff.php');

include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/ThreeWay.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/ThreeWay/BlockBuilder.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/ThreeWay/Op/Base.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/ThreeWay/Op/Copy.php');

include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Renderer.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Renderer/table.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Renderer/Inline.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Renderer/Unified.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Renderer/Context.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Renderer/Unified/Colored.php');

include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Op/Base.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Op/Delete.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Op/Change.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Op/Copy.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Op/Add.php');

include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Exception.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Mapped.php');
