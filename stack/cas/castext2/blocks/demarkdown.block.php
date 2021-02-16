<?php
// This file is part of Stateful
//
// Stateful is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stateful is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stateful.  If not, see <http://www.gnu.org/licenses/>.
defined('MOODLE_INTERNAL') || die();


require_once(__DIR__ . '/../block.interface.php');
require_once($CFG->libdir . '/weblib.php');


/** 
 * Block that will simply convert anything inside it from Markdown 
 * to HTML. Allowing certain types of mixed contents. Primarily exists
 * to map the problem of Markdown back to the normal HTML-processing.
 */
class stack_cas_castext2_demarkdown extends stack_cas_castext2_block {

    public function compile($format, $options): ?string {
        // Basically mark the contetns for post-processing.
        $r = '["demarkdown"';

        
        foreach ($this->children as $item) {
            $c = $item->compile($format, $options);
            if ($c !== null) {
                $r .= ',' . $c;
            }   
        }
        
        $r .= ']';

        return $r;
    }

    public function is_flat(): bool {
        return false;
    }
    
    public function postprocess(array $params, castext2_processor $processor=null): string {
    	// First collapse the content.
    	$content    = '';
        for ($i = 1; $i < count($params); $i++) {
            if (is_array($params[$i])) {
                $content .= $processor->process($params[$i][0], $params[$i]);
            } else {
                $content .= $params[$i];
            }
		}
        
        $content = markdown_to_html($content);
        // Note that at this point most of the interesting chars are entities.
        // We need to revert some of those conversions to allow later processign to 
        // detect LaTeX for MathJax.
        // This makes the text such that it should not be reprocessed in any Markdown 
        // filter luckily we will not do that.
        $content = str_replace(['&#92;','&#40;','&#91;','&#123;','&#41;','&#93;','&#125;','&#95;'],["\\",'(','[','{',')',']','}','_'], $content);

        return $content;
    }

    public function validate_extract_attributes(): array {
        return array();
    }
}