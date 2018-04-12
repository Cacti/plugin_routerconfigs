<?php

//require_once 'Text/Diff/Renderer.php';
//require_once 'Text/Diff/Renderer/marker.php';

// Marker diff renderer class at bottom of file!

/**
 * "Table" diff renderer.
 *
 * Modified by Steven Whitbread to create a table layout of diff results. Creates three column table with classes to style table on various diff operations.
 * Makes use of marker renderer, which just marks the changes in final and orig with out merging them like in the Inline renderer.
 *
 *
 * $Horde: framework/Text_Diff/Diff/Renderer/inline.php,v 1.16 2006/01/08 00:06:57 jan Exp $
 *
 * @author  Ciprian Popovici
 * @package Horde_Text_Diff
 */
class Horde_Text_Diff_Renderer_table extends Horde_Text_Diff_Renderer {

    /**
     * Number of leading context "lines" to preserve.
     */
    var $_leading_context_lines = 10000;

    /**
     * Number of trailing context "lines" to preserve.
     */
    var $_trailing_context_lines = 10000;

    /**
     * Prefix for missed text.
     */
    var $_mis_prefix = '<mis>';

    /**
     * Suffix for missed text.
     */
    var $_mis_suffix = '</mis>';

    /**
     * Prefix for inserted text.
     */
    var $_ins_prefix = '<ins>';

    /**
     * Suffix for inserted text.
     */
    var $_ins_suffix = '</ins>';

    /**
     * Prefix for deleted text.
     */
    var $_del_prefix = '<del>';

    /**
     * Suffix for deleted text.
     */
    var $_del_suffix = '</del>';

    /**
     * Header for each change block.
     */
    var $_block_header = '';

    /**
     * What are we currently splitting on? Used to recurse to show word-level
     * changes.
     */
    var $_split_level = 'lines';

    function _startDiff() {
	return '';
    }

    function _endDiff() {
		return '';
    }


    function _lines($lines, $prefix = ' ', $encode = true)
    {
        if ($encode) {
            array_walk($lines, array(&$this, '_encode'));
        }

        if ($this->_split_level == 'words') {
            return implode('', $lines);
        } else {
            return implode("\n", $lines). "\n";
        }
    }

    function _added($lines)
    {
        array_walk($lines, array(&$this, '_encode'));
        $lines[0] = $this->_ins_prefix . $lines[0];
        $lines[count($lines) - 1] .= $this->_ins_suffix;
        return '<tr><td class=noc></td><td class=mid></td><td class=add>'.$this->_lines($lines, ' ', false).'</td></tr>';
    }

    function _addedInline($lines)
    {
        array_walk($lines, array(&$this, '_encode'));
        $lines[0] = $this->_ins_prefix . $lines[0];
        $lines[count($lines) - 1] .= $this->_ins_suffix;
        return $this->_lines($lines, ' ', false);
    }

    function _deleted($lines, $words = false)
    {
        array_walk($lines, array(&$this, '_encode'));
        $lines[0] = $this->_del_prefix . $lines[0];
        $lines[count($lines) - 1] .= $this->_del_suffix;
        return '<tr><td class="del">'.$this->_lines($lines, ' ', false).'</td><td class=mid></td><td class=noc></td></tr>';
    }

   function _deletedInline($lines, $words = false)
    {
        array_walk($lines, array(&$this, '_encode'));
        $lines[0] = $this->_del_prefix . $lines[0];
        $lines[count($lines) - 1] .= $this->_del_suffix;
        return $this->_lines($lines, ' ', false);
    }

    function _splitOnWords($string, $newlineEscape = "\n")
    {
        $words = array();
        $length = strlen($string);
        $pos = 0;

        while ($pos < $length) {
            // Eat a word with any preceding whitespace.
            $spaces = strspn(substr($string, $pos), " \n");
            $nextpos = strcspn(substr($string, $pos + $spaces), " \n");
            $words[] = str_replace("\n", $newlineEscape, substr($string, $pos, $spaces + $nextpos));
            $pos += $spaces + $nextpos;
        }

        return $words;
    }

    function _encode(&$string)
    {
        $string = htmlspecialchars($string);
    }


    function _changed($orig, $final)
    {
        $text1 = implode("\n", $orig);
        $text2 = implode("\n", $final);

	$count_orig = count($orig);
	$count_final = count($final);
	$count_change = min($count_orig, $count_final);
	$count_max = max($count_orig, $count_final);

	$html = '';

        /* These "if" statments catch conditions where the diff engine seems to return added lines as changes,
         * this is seen by a difference between the count of the final array and the orig array. Also catch deleted lines (if there are any, not sure?).
         * it only seems to happen after the line with changes, not before.
        */
        for($i = 0; $i < $count_max; $i++){
            /* Non-printing newline marker. */
            $nl = "\0";

            /* Get original value, if less than array size */
            $source_orig = ($i < $count_orig ? $orig[$i] : null);

            /* Get final value, if less than array size */
            $source_final = ($i < $count_final ? $final[$i] : null);

            if ($source_orig !== null && $source_final !== null) {
                /* We want to split on word boundaries, but we need to
                 * preserve whitespace as well. Therefore we split on words,
                 * but include all blocks of whitespace in the wordlist. */

                $output_source = $source_orig;
		$output_final = $source_final;

                $class_source = 'chg';
		$class_final = 'chg';
            } elseif ($source_orig === null) {
                    $class_source = 'mis';
                    $class_final = 'add';
                    $output_source = '';
                    $output_final =  $final[$i];
            } elseif ($source_final === null) {
                    $class_source = 'del';
                    $class_final = 'mis';
                    $output_source = $orig[$i];
                    $output_final = '';
            }

            if ($output_source > '') {
		$output_source = $this->_del_prefix . $output_source . $this->_del_suffix;
            //} else {
            //    $output_source = $this->_mis_prefix . $output_source . $this->_mis_suffix;
            }

            if ($output_final > '') {
                $output_final = $this->_ins_prefix . $output_final . $this->_ins_suffix;
            //} else {
            //    $output_final = $this->_mis_prefix . $output_final . $this->_mis_suffix;
            }

            $html .= '<tr><td class="'.$class_source.'">'.$output_source.'</td><td class=mid></td>';
            $html .= '<td class="'.$class_final.'">'.$output_final.'</td></tr>';
        }
        return $html;
    }

    function _context($lines)
    {
        $linesa = $this->_lines($lines);
        $output = '';
        foreach($lines as $key => $value){
            $output .= '<tr class=noc><td>' . $value .'</td><td class=mid></td><td>'. $value.'</td></tr>';
        }
        return $output;
    }

    function _startBlock($header)
    {
        return '';
    }

    function _endBlock()
    {
        return '';
    }

}


/**
 * "Marker" diff renderer.
 *
 * Modified by Steven Whitbread 2006/07/11 to allow the marking of the final text with additions and original text with deletes. Work's on words not lines
 * This is help with the table diff layout so that the "object changetype" is not a merger of final and orig, but higlights the diferences seperately.
 * returns $output->final and $output->orig , where final will show inserts and orig will show deletes.
 *
 * This class renders diffs in the Wiki-style "inline" format.
 *
 * $Horde: framework/Text_Diff/Diff/Renderer/inline.php,v 1.16 2006/01/08 00:06:57 jan Exp $
 *
 * @author  Ciprian Popovici
 * @package Text_Diff
 */
class Horde_Text_Diff_Renderer_marker extends Horde_Text_Diff_Renderer {

    /**
     * Number of leading context "lines" to preserve.
     */
    var $_leading_context_lines = 10000;

    /**
     * Number of trailing context "lines" to preserve.
     */
    var $_trailing_context_lines = 10000;

    /**
     * Prefix for inserted text.
     */
    var $_ins_prefix = '<ins>';

    /**
     * Suffix for inserted text.
     */
    var $_ins_suffix = '</ins>';

    /**
     * Prefix for deleted text.
     */
    var $_del_prefix = '<del>';

    /**
     * Suffix for deleted text.
     */
    var $_del_suffix = '</del>';

    /**
     * Header for each change block.
     */
    var $_block_header = '';

    /**
     * What are we currently splitting on? Used to recurse to show word-level
     * changes.
     */
    var $_split_level = 'words';

    function _blockHeader($xbeg, $xlen, $ybeg, $ylen)
    {
        return $this->_block_header;
    }

    function _startBlock($header)
    {
        return $header;
    }

    function _lines($lines, $prefix = ' ', $encode = true)
    {
        if ($encode) {
            array_walk($lines, array(&$this, '_encode'));
        }

        if ($this->_split_level == 'words') {
            return implode('', $lines);
        } else {
            return implode("\n", $lines) . "\n";
        }
    }

    function _added($lines)
    {
        array_walk($lines, array(&$this, '_encode'));
        $lines[0] = $this->_ins_prefix . $lines[0];
        $lines[count($lines) - 1] .= $this->_ins_suffix;
        return $this->_lines($lines, ' ', false);
    }

    function _deleted($lines, $words = false)
    {
        array_walk($lines, array(&$this, '_encode'));
        $lines[0] = $this->_del_prefix . $lines[0];
        $lines[count($lines) - 1] .= $this->_del_suffix;
        return $this->_lines($lines, ' ', false);
    }

    function _changed($orig, $final)
    {
        /* If we've already split on words, don't try to do so again - just
         * display. */
        //if ($this->_split_level == 'words') {
            $prefix = '';
            while ($orig[0] !== false && $final[0] !== false &&
                   substr($orig[0], 0, 1) == ' ' &&
                   substr($final[0], 0, 1) == ' ') {
                $prefix .= substr($orig[0], 0, 1);
                $orig[0] = substr($orig[0], 1);
                $final[0] = substr($final[0], 1);
            }
            $temp->final = $this->_added($final);
            $temp->orig = $this->_deleted($orig);
            return $temp;
        //}

    }

    function _splitOnWords($string, $newlineEscape = "\n")
    {
        $words = array();
        $length = strlen($string);
        $pos = 0;

        while ($pos < $length) {
            // Eat a word with any preceding whitespace.
            $spaces = strspn(substr($string, $pos), " \n");
            $nextpos = strcspn(substr($string, $pos + $spaces), " \n");
            $words[] = str_replace("\n", $newlineEscape, substr($string, $pos, $spaces + $nextpos));
            $pos += $spaces + $nextpos;
        }

        return $words;
    }

    function _encode(&$string)
    {
        $string = htmlspecialchars($string);
    }

    function _block1($xbeg, $xlen, $ybeg, $ylen, &$edits)
    {
        //Modified to keep orig and final seperate, but highlight changes
        $marked['origin'] = array();
        $marked['final'] = array();

        foreach ($edits as $edit) {
            switch (strtolower(get_class($edit))) {
            case 'text_diff_op_copy':
                $marked['origin'][] = implode(' ',$edit->orig);
                $marked['final'][] = implode(' ',$edit->final);
                break;

            case 'text_diff_op_add':
                $marked['final'][] = $this->_added($edit->final);
                break;

            case 'text_diff_op_delete':
                $marked['origin'][] = $this->_deleted($edit->orig);
                break;

            case 'text_diff_op_change':
                $temp3 = $this->_changed($edit->orig, $edit->final);
                $marked['final'][] = $temp3->final;
                $marked['origin'][] = $temp3->orig;
                break;
            }
        }
       $output = new stdClass();
        $output->final = implode(' ', $marked['final']);
        $output->orig = implode(' ', $marked['origin']);
        return $output;
    }

    function render1($diff)
    {
        $xi = $yi = 1;
        $block = false;
        $context = array();

        $nlead = $this->_leading_context_lines;
        $ntrail = $this->_trailing_context_lines;

        $output = array();

        $diffs = $diff->getDiff();
        foreach ($diffs as $i => $edit) {
            if (is_a($edit, 'Text_Diff_Op_copy')) {
                if (is_array($block)) {
                    $keep = $i == count($diffs) - 1 ? $ntrail : $nlead + $ntrail;
                    if (count($edit->orig) <= $keep) {
                        $block[] = $edit;
                    } else {
                        if ($ntrail) {
                            $context = array_slice($edit->orig, 0, $ntrail);
                            $block[] = new Text_Diff_Op_copy($context);
                        }
                        $output[] = $this->_block($x0, $ntrail + $xi - $x0,
                                                  $y0, $ntrail + $yi - $y0,
                                                  $block);
                    }
                }
                $context = $edit->orig;
            } else {
                if (!is_array($block)) {
                    $context = array_slice($context, count($context) - $nlead);
                    $x0 = $xi - count($context);
                    $y0 = $yi - count($context);
                    $block = array();
                    if ($context) {
                        $block[] = new Text_Diff_Op_copy($context);
                    }
                }
                $block[] = $edit;
            }

            if ($edit->orig) {
                $xi += count($edit->orig);
            }
            if ($edit->final) {
                $yi += count($edit->final);
            }
        }

        if (is_array($block)) {
            $output .= $this->_block($x0, $ntrail + $xi - $x0,
                $y0, $ntrail + $yi - $y0, $block);
        }
        //returns object
        return $output;
    }


}
