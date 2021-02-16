<?php
// This file is part of STACK
//
// STACK is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// STACK is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with STACK.  If not, see <http://www.gnu.org/licenses/>.
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../block.interface.php');
require_once(__DIR__ . '/../../../utils.class.php');

/** 
 * Special block allowing swithing the injection formating back to RAW.
 * Useful if writing something one does not want to be escaped on output
 * within a context that requires escaping. For example, JavaScript
 * with injected values inside Markdown context.
 */
class stack_cas_castext2_htmlformat extends stack_cas_castext2_block {

    public function compile($format, $options): ?string {
        // Basically we change the value of $format for this subtree.
        // Note that the jsxgraph block does this automatically.
        $r = '';
        $flat = $this->is_flat();
        if (!$flat) {
            $r .= '["%root",';
        } else {
            $r .= 'sconcat(';
        }

        $items = array();
        foreach ($this->children as $item) {
            $c = $item->compile(castext2_parser_utils::RAWFORMAT, $options);
            if ($c !== null) {
                $items[] = $c;
            }   
        }
        $r .= implode(',', $items);

        if (!$flat) {
            $r .= ']';
        } else {
            $r .= ')';
        }
        return $r;
    }

    public function is_flat(): bool {
        // Now then the problem here is that the flatness depends on the flatness of 
        // the blocks contents. If they all generate strings then we are flat but if not...
        $flat = true;

        foreach ($this->children as $child) {
            $flat = $flat && $child->is_flat();
        }

        return $flat;
    }
    
    public function validate_extract_attributes(): array {
        return array();
    }
}