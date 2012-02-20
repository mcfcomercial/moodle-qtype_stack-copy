<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Interaction element for inputting true/false using a select dropdown.
 *
 * @copyright  2012 University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stack_interaction_boolean extends stack_interaction_element {
    const F = 'false';
    const T = 'true';
    const NA = '';

    public function __construct($name, $teacheranswer, $parameters) {
        if (!in_array($teacheranswer, array(self::T, self::F))) {
            $teacheranswer = self::NA;
        }
        parent::__construct($name, $teacheranswer, $parameters);
    }

    public function get_xhtml($studentanswer, $fieldname, $readonly) {
        $choices = array(
            self::F => stack_string('false'),
            self::T => stack_string('true'),
            self::NA => stack_string('notanswered'),
        );

        $disabled = '';
        if ($readonly) {
            $disabled = ' disabled="disabled"';
        }

        $output = '<select name="' . $fieldname . '"' . $disabled . '>';
        foreach ($choices as $value => $choice) {
            $selected = '';
            if ($value === $studentanswer) {
                $selected = ' selected="selected"';
            }

            $output .= '<option value="' . $value . '"' . $selected . '>' .
                    htmlspecialchars($choice) . '</option>';
        }
        $output .= '</select>';

        return $output;
    }

    /**
     * Return the default values for the parameters.
     * @return array parameters` => default value.
     */
    public static function get_parameters_defaults() {
        return array(
                'mustVerify'     => false,
                'hideFeedback'   => true);
    }

}
