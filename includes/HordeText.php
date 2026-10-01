<?php

declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

// Translation library
require_once(__DIR__ . '/../Text/Translation.php');
require_once(__DIR__ . '/../Text/Translation/Autodetect.php');
require_once(__DIR__ . '/../Text/Translation/Handler.php');
require_once(__DIR__ . '/../Text/Translation/Exception.php');
require_once(__DIR__ . '/../Text/Translation/Handler/Gettext.php');

// Exception library
require_once(__DIR__ . '/../Text/Exception.php');
require_once(__DIR__ . '/../Text/Exception/Pear.php');
require_once(__DIR__ . '/../Text/Exception/Translation.php');
require_once(__DIR__ . '/../Text/Exception/PermissionDenied.php');
require_once(__DIR__ . '/../Text/Exception/LastError.php');
require_once(__DIR__ . '/../Text/Exception/NotFound.php');
require_once(__DIR__ . '/../Text/Exception/Wrapped.php');

// Util Library
require_once(__DIR__ . '/../Text/Util/String.php');
require_once(__DIR__ . '/../Text/Util/Domhtml.php');
require_once(__DIR__ . '/../Text/Util/Array.php');
require_once(__DIR__ . '/../Text/Util/Util.php');
require_once(__DIR__ . '/../Text/Util/Variables.php');
require_once(__DIR__ . '/../Text/Util/Array/Sort/Helper.php');
require_once(__DIR__ . '/../Text/Util/String/Transliterate.php');

// Diff library
require_once(__DIR__ . '/../Text/Diff/Engine/xdiff.php');
require_once(__DIR__ . '/../Text/Diff/Engine/string.php');
require_once(__DIR__ . '/../Text/Diff/Engine/native.php');
require_once(__DIR__ . '/../Text/Diff/Engine/shell.php');

require_once(__DIR__ . '/../Text/Diff.php');

require_once(__DIR__ . '/../Text/Diff/ThreeWay.php');
require_once(__DIR__ . '/../Text/Diff/ThreeWay/BlockBuilder.php');
require_once(__DIR__ . '/../Text/Diff/ThreeWay/Op/Base.php');
require_once(__DIR__ . '/../Text/Diff/ThreeWay/Op/Copy.php');

require_once(__DIR__ . '/../Text/Diff/Renderer.php');
require_once(__DIR__ . '/../Text/Diff/Renderer/table.php');
require_once(__DIR__ . '/../Text/Diff/Renderer/Inline.php');
require_once(__DIR__ . '/../Text/Diff/Renderer/Unified.php');
require_once(__DIR__ . '/../Text/Diff/Renderer/Context.php');
require_once(__DIR__ . '/../Text/Diff/Renderer/Unified/Colored.php');

require_once(__DIR__ . '/../Text/Diff/Op/Base.php');
require_once(__DIR__ . '/../Text/Diff/Op/Delete.php');
require_once(__DIR__ . '/../Text/Diff/Op/Change.php');
require_once(__DIR__ . '/../Text/Diff/Op/Copy.php');
require_once(__DIR__ . '/../Text/Diff/Op/Add.php');

require_once(__DIR__ . '/../Text/Diff/Exception.php');
require_once(__DIR__ . '/../Text/Diff/Mapped.php');
