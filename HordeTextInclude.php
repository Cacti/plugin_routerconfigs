<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2019 The Cacti Group                                 |
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

/* Translation library */
include_once(__DIR__  . '/Text/Translation.php');
include_once(__DIR__  . '/Text/Translation/Autodetect.php');
include_once(__DIR__  . '/Text/Translation/Handler.php');
include_once(__DIR__  . '/Text/Translation/Exception.php');
include_once(__DIR__  . '/Text/Translation/Handler/Gettext.php');

/* Exception library */
include_once(__DIR__  . '/Text/Exception.php');
include_once(__DIR__  . '/Text/Exception/Pear.php');
include_once(__DIR__  . '/Text/Exception/Translation.php');
include_once(__DIR__  . '/Text/Exception/PermissionDenied.php');
include_once(__DIR__  . '/Text/Exception/LastError.php');
include_once(__DIR__  . '/Text/Exception/NotFound.php');
include_once(__DIR__  . '/Text/Exception/Wrapped.php');

/* Util Library */
include_once(__DIR__  . '/Text/Util/String.php');
include_once(__DIR__  . '/Text/Util/Domhtml.php');
include_once(__DIR__  . '/Text/Util/Array.php');
include_once(__DIR__  . '/Text/Util/Util.php');
include_once(__DIR__  . '/Text/Util/Variables.php');
include_once(__DIR__  . '/Text/Util/Array/Sort/Helper.php');
include_once(__DIR__  . '/Text/Util/String/Transliterate.php');

/* Diff library */
include_once(__DIR__  . '/Text/Diff/Engine/xdiff.php');
include_once(__DIR__  . '/Text/Diff/Engine/string.php');
include_once(__DIR__  . '/Text/Diff/Engine/native.php');
include_once(__DIR__  . '/Text/Diff/Engine/shell.php');

include_once(__DIR__  . '/Text/Diff.php');

include_once(__DIR__  . '/Text/Diff/ThreeWay.php');
include_once(__DIR__  . '/Text/Diff/ThreeWay/BlockBuilder.php');
include_once(__DIR__  . '/Text/Diff/ThreeWay/Op/Base.php');
include_once(__DIR__  . '/Text/Diff/ThreeWay/Op/Copy.php');

include_once(__DIR__  . '/Text/Diff/Renderer.php');
include_once(__DIR__  . '/Text/Diff/Renderer/table.php');
include_once(__DIR__  . '/Text/Diff/Renderer/Inline.php');
include_once(__DIR__  . '/Text/Diff/Renderer/Unified.php');
include_once(__DIR__  . '/Text/Diff/Renderer/Context.php');
include_once(__DIR__  . '/Text/Diff/Renderer/Unified/Colored.php');

include_once(__DIR__  . '/Text/Diff/Op/Base.php');
include_once(__DIR__  . '/Text/Diff/Op/Delete.php');
include_once(__DIR__  . '/Text/Diff/Op/Change.php');
include_once(__DIR__  . '/Text/Diff/Op/Copy.php');
include_once(__DIR__  . '/Text/Diff/Op/Add.php');

include_once(__DIR__  . '/Text/Diff/Exception.php');
include_once(__DIR__  . '/Text/Diff/Mapped.php');
