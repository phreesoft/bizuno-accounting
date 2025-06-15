<?php
/*
 * This class maps the structure to HTML syntax using the jQuery easyUI UI
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.TXT.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please refer to http://www.phreesoft.com for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2024, PhreeSoft, Inc.
 * @license    http://opensource.org/licenses/OSL-3.0  Open Software License (OSL 3.0)
 * @version    6.x Last Update: 2023-08-30
 * @filesource /view/easyUI/html5.php
 */

namespace bizuno;

final class html5 {

//  const bizunoHelp = 'https://www.phreesoft.com';

    private $pageT   = 50;  // page layout minimum pixel height
    private $pageB   = 35;
    private $pageL   = 175;
    private $pageR   = 175;
    public  $jsHead  = [];
    public  $jsBody  = [];
    public  $jsReady = [];
    public  $jsResize= [];

    function __construct() {
        $tinyMCE_key      = defined('BIZUNO_TINYMCE_KEY') ? BIZUNO_TINYMCE_KEY : 'no-api-key';
        $this->htmlEditor = "https://cdn.tiny.cloud/1/$tinyMCE_key/tinymce/5/tinymce.min.js";
    }

    /**
     * This function builds an array of div elements based on a type and structure
     * @param string $output - running output buffer
     * @param array $data - data structure to be processed (typically within the div)
     * @param string $type - [default: divs] specifies where to pull source, choices are 'divs', 'head', 'foot'
     */
    public function buildDivs($data, $type='divs') {
        msgDebug("\nEntering buildDivs with type = $type");
        $html = '';
        if (empty($data[$type])) { return $html; }
        $data[$type] = sortOrder($data[$type]);
        $close = $this->buildDivProp($html, $data);
        foreach ($data[$type] as $prop) { $this->buildDiv($html, $prop); }
        if ($close) { $html .= "</div>\n"; }
        return $html;
    }

    private function buildDivProp(&$output, $prop) {
        if (empty($prop['classes']) && empty($prop['styles']) && empty($prop['options']) && empty($prop['attr'])) { return false; }
        $prop['attr']['type'] = 'div';
        $output .= $this->render(!empty($prop['attr']['id'])?$prop['attr']['id']:'', $prop);
        return true;
    }

    /**
     * This function builds a div element based on a type and structure
     * @param string $output - running output buffer
     * @param array $data - data structure to be processed (typically within the div)
     * @param array $prop - type of div to build and structure
     */
    public function buildDiv(&$output, $prop) {
        global $viewData;
        if (!empty($prop['hidden'])) { return ''; }
        if ( empty($prop['type']))   { $prop['type'] = 'template'; } // default
        msgDebug("\nEntering buildDiv of type {$prop['type']}");
        switch ($prop['type']) {
            case 'accordion':
//                if (isset($prop['key'])) { $prop = array_merge($viewData['accordion'][$prop['key']], ['id'=>$prop['key']]); }
                $this->layoutAccordion($output, $prop);
                break;
            case 'address':  $this->layoutAddress($output, $prop); break;
            case 'attach':   $this->layoutAttach($output, $prop); break;
            case 'datagrid': $this->layoutDatagrid($output, $prop, $prop['key']); break;
            case 'divs':     $output .= $this->buildDivs($prop); break;
            case 'fields':   $output .= $this->layoutFields($viewData, $prop); break;
            case 'form':     $output .= $this->render($prop['key'], $viewData['forms'][$prop['key']]); break;
            case 'html':     $output .= $this->layoutHTML($prop); break;
            case 'list':     $output .= $this->layoutList($viewData, $prop); break;
            case 'menu':     $this->menu($output, $prop); break;
            case 'panel':    $this->layoutPanel($output, $prop); break;
            case 'payment':  $this->layoutPayment($output, $prop); break;
            case 'payments':
                foreach ($viewData['payments'] as $methID) {
                    $fqcn   = "\\bizuno\\$methID";
                    bizAutoLoad(BIZBOOKS_ROOT."controllers/payment/methods/$methID/$methID.php", $fqcn);
                    $totSet = getModuleCache('payment','methods',$methID,'settings');
                    $totals = new $fqcn($totSet);
                    $output .= $totals->render($viewData);
                }
                break;
            case 'table':
                if (isset($prop['key'])) {
                    $prop = array_merge($prop, $viewData['tables'][$prop['key']]);
                    $prop['attr']['id'] = $prop['key'];
                }
                $this->layoutTable($output, $prop);
                break;
            case 'tabs': $this->layoutTab($output, $prop); break;
            case 'toolbar':
                if (isset($prop['key'])) {
                    $tbProps = !empty($viewData['toolbars'][$prop['key']]) ? $viewData['toolbars'][$prop['key']] : [];
                    $prop = array_merge((array)$viewData['toolbars'][$prop['key']], ['id' => $prop['key']]);
                }
                $this->layoutToolbar($output, $prop);
                break;
            case 'totals':
                foreach ($prop['content'] as $methID) {
                    $path = getModuleCache('phreebooks', 'totals', $methID, 'path');
                    $fqcn = "\\bizuno\\$methID";
                    bizAutoLoad("{$path}$methID.php", $fqcn);
                    $totSet = getModuleCache('phreebooks','totals',$methID,'settings');
                    $totals = new $fqcn($totSet);
                    $output .= $totals->render($viewData);
                }
                break;
            case 'tree':
                if (isset($prop['key'])) { $prop['el'] = array_merge($viewData['tree'][$prop['key']], ['id' => $prop['key']]); }
                $this->layoutTree($output, $prop['el']);
                break;
            default:
/*          case 'template': // DEPRECATED
                if (!isset($prop['settings']) && isset($prop['attr'])) { $prop['settings'] = $prop['attr']; } // for legacy
                if (isset($prop['src']) && file_exists($prop['src'])) { require ($prop['src']); }
                break; */
        }
    }

    public function render($id = '', $prop = []) {
    if (!is_array($prop)) { return msgAdd("received string as array for field $id"); }
        if (empty($prop['attr']['type'])) { $prop['attr']['type'] = 'text'; } // assume text if no type
        $field = '';
        if (isset($prop['hidden']) && $prop['hidden']) { return $field; }
        if (isset($prop['icon'])) { return $this->menuIcon($id, $prop); }
        switch ($prop['attr']['type']) {
            case 'a':
            case 'address':
            case 'article':
            case 'aside':
            case 'b':
            case 'em':
            case 'fieldset':
            case 'footer':
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
            case 'label':
            case 'p':
            case 'span':
            case 'u':           return $this->htmlElBoth($id, $prop);
            case 'br':
            case 'hr':
            case 'img':         return $this->htmlElEmpty($id, $prop);
            case 'div':
            case 'form':
            case 'section':
            case 'header':
            case 'li':
            case 'ol':
            case 'td':
            case 'th':
            case 'tr':
            case 'thead':
            case 'tbody':
            case 'tfoot':
            case 'ul':          return $this->htmlElOpen($id, $prop);
            case 'badge':       return $this->inputBadge($id, $prop);
            case 'button':      return $this->inputButton($id, $prop);
//          case 'iframe':      return $this->layoutIframe($output, $id, $prop);
            case 'checkbox':    return $this->inputCheckbox($id, $prop);
            case 'color':       return $this->inputColor($id, $prop);
            case 'contact':     return $this->inputContact($id, $prop);
            case 'country':     return $this->inputCountry($id, $prop);
            case 'currency':    return $this->inputCurrency($id, $prop);
            case 'date':
            case 'datetime-local':
            case 'datetime':    return $this->inputDate($id, $prop);
            case 'email':       return $this->inputEmail($id, $prop);
            case 'decimal':
            case 'float':
            case 'number':      return $this->inputNumber($id, $prop);
            case 'file':        return $this->inputFile($id, $prop);
            case 'hidden':
            case 'linkimg': // used in custom fields contacts/inventory
            case 'phone':
            case 'tel':
            case 'text':
            case 'time':        return $this->inputText($id, $prop);
            case 'integer':
            case 'month':
            case 'week':
            case 'year':        return $this->inputNumber($id, $prop, 0);
            case 'inventory':   return $this->inputInventory($id, $prop);
            case 'password':    return $this->inputPassword($id, $prop);
            case 'ledger':      return $this->inputGL($id, $prop);
            case 'radio':       return $this->inputRadio($id, $prop);
            case 'raw':         return $this->inputRaw($id, $prop);
            case 'selCurrency': return $this->selCurrency($id, $prop);
            case 'selNoYes':    return $this->selNoYes($id, $prop);
            case 'select':      return $this->inputSelect($id, $prop);
            case 'spinner':     return $this->inputSpinner($id, $prop);
            case 'html':
            case 'htmlarea': // @todo - need to deprecate htmlarea, replace with either html or textarea
            case 'textarea':    return $this->inputTextarea($id, $prop);
            case 'table':       return $this->layoutTable($id, $prop);
            case 'tax':         return $this->inputTax($id, $prop);


            case 'abbr': //Defines an abbreviation or an acronym
            case 'area': //Defines an area inside an image-map
            case 'audio': //Defines sound content
            case 'base': //Specifies the base URL/target for all relative URLs in a document
            case 'bdi': //Isolates a part of text that might be formatted in a different direction from other text outside it
            case 'bdo': //Overrides the current text direction
            case 'blockquote': //Defines a section that is quoted from another source
            case 'canvas': //Used to draw graphics, on the fly, via scripting (usually JavaScript)
            case 'caption': //Defines a table caption
            case 'cite': //Defines the title of a work
            case 'code': //Defines a piece of computer code
            case 'col': //Specifies column properties for each column within a case 'colgroup': element
            case 'colgroup': //Specifies a group of one or more columns in a table for formatting
            case 'datalist': //Specifies a list of pre-defined options for input controls
            case 'dd': //Defines a description/value of a term in a description list
            case 'del': //Defines text that has been deleted from a document
            case 'details': //Defines additional details that the user can view or hide
            case 'dfn': //Represents the defining instance of a term
            case 'dl': //Defines a description list
            case 'dt': //Defines a term/name in a description list
            case 'embed': //Defines a container for an external (non-HTML) application
            case 'figcaption': //Defines a caption for a case 'figure': element
            case 'figure': //Specifies self-contained content
            case 'i': //Defines a part of text in an alternate voice or mood
            case 'ins': //Defines a text that has been inserted into a document
            case 'kbd': //Defines keyboard input
            case 'keygen': //Defines a key-pair generator field (for forms)
            case 'label': //Defines a label for an case 'input': element
            case 'link': //Defines the relationship between a document and an external resource (most used to link to style sheets)
            case 'main': //Specifies the main content of a document
            case 'map': //Defines a client-side image-map
            case 'mark': //Defines marked/highlighted text
            case 'menu': //Defines a list/menu of commands
            case 'menuitem': //Defines a command/menu item that the user can invoke from a popup menu
            case 'meta': //Defines metadata about an HTML document
            case 'meter': //Defines a scalar measurement within a known range (a gauge)
            case 'object': //Defines an embedded object
            case 'output': //Defines the result of a calculation
            case 'param': //Defines a parameter for an object
            case 'picture': //Defines a container for multiple image resources
            case 'pre': //Defines preformatted text
            case 'progress': //Represents the progress of a task
            case 'q': //Defines a short quotation
            case 'range': // Bizuno added
            case 'reset': // Bizuno Added
            case 'rp': //Defines what to show in browsers that do not support ruby annotations
            case 'rt': //Defines an explanation/pronunciation of characters (for East Asian typography)
            case 'ruby': //Defines a ruby annotation (for East Asian typography)
            case 's': //Defines text that is no longer correct
            case 'samp': //Defines sample output from a computer program
            case 'script': //Defines a client-side script
            case 'search': // Bizuno Added
            case 'small': //Defines smaller text
            case 'source': //Defines multiple media resources for media elements (case 'video': and case 'audio':)
            case 'strong': //Defines important text
            case 'style': //Defines style information for a document
            case 'sub': //Defines subscripted text
            case 'summary': //Defines a visible heading for a case 'details': element
            case 'sup': //Defines superscripted text
            case 'time': //Defines a date/time
            case 'title': //Defines a title for the document
            case 'track': //Defines text tracks for media elements (case 'video': and case 'audio':)
            case 'url': // Bizuno Added
            case 'var': //Defines a variable
            case 'video': //Defines a video or movie
            case 'wbr': //Defines a possible line-break
            // special cases and adjustments
            default:
                msgDebug("\nUndefined Element type: {$prop['attr']['type']} with properties: " . print_r($prop, true));
                msgAdd("Undefined element type: {$prop['attr']['type']}", 'trap');
        }
        if (isset($prop['break']) && $prop['break'] && $prop['attr']['type'] <> 'hidden') { $field .= "<br />\n"; }
        if (isset($prop['js'])) { $this->jsBody[] = $prop['js']; }
        return $field;
    }

    /***************************** Elements ******************/

    /**
     * Creates an full html element with separate opening and closing tags, i.e. <...> something </...>
     * @param string $id - element id
     * @param array $prop - element properties
     * @return string - html element ready to send to browser
     */
    private function htmlElBoth($id, $prop) {
        if (isset($prop['attr']['value'])) {
            $value = isset($prop['format']) ? viewFormat($prop['attr']['value'], $prop['format']) : $prop['attr']['value'];
//          $value = str_replace('"', '&quot;', $value); // commented out as this prevents html from within the tags
            unset($prop['attr']['value']);
        } else {
            $value = '&nbsp;';
        }
        $type = $prop['attr']['type'];
        unset($prop['attr']['type']);
        $output = "<$type" . $this->addAttrs($prop) . ">" . $value . "</$type>\n";
        return $output . (!empty($prop['break']) ? '<br />' : '');
    }

    /**
     * Creates an element that is self closing, i.e. <... />
     * @param string $id - element id
     * @param array $prop - element properties
     * @return string - html element ready to send to browser
     */
    public function htmlElEmpty($id, $prop) {
        return "<{$prop['attr']['type']}" . $this->addAttrs($prop) . " />";
    }

    /**
     * Builds a HTML open element, i.e no closing tag </...>
     * @param string $id - element id
     * @param array $prop - element properties
     * @return string - html element ready to send to browser
     */
    private function htmlElOpen($id, $prop) {
        $this->addID($id, $prop);
        $type = $prop['attr']['type'];
        unset($prop['attr']['type']);
        return "<$type" . $this->addAttrs($prop) . ">";
    }

    /*     * *************************** Headings ***************** */

    // H1-H6

    /*     * *************************** Tables ***************** */
    public function div() {
        $field .= '<' . $prop['attr']['type'];
        foreach ($prop['attr'] as $key => $value) {
            if ($key <> 'type') {
                $field .= ' ' . $key . '="' . str_replace('"', '\"', $value) . '"';
            } // was str_replace('"', '&quot;', $value)
        }
        $field .= ">";
    }

    /**
     * This function generates the tables pulled from the current structure, position: $data['tables'][$idx]
     * @param array $output - running HTML string to render the page
     * @param string $data - The structure source data to pull from
     * @param array $idx - The index in $data to grab the structure to build
     * @return string - HTML formatted EasyUI tables appended to $output
     */
    function table(&$output, $id = '', $prop = []) {
        $output .= $this->render($id, $prop) . "\n";
        if (!empty($prop['thead'])) { $this->tableRows($output, $prop['thead']) . "</thead>\n"; }
        if (!empty($prop['tbody'])) { $this->tableRows($output, $prop['tbody']) . "</tbody>\n"; }
        if (!empty($prop['tfoot'])) { $this->tableRows($output, $prop['tfoot']) . "</tfoot>\n"; }
        $output .= "</table><!-- End table $id -->\n";
    }

    function tableRows(&$output, $prop) {
        $output = $this->render('', $prop) . "\n";
        foreach ($prop['tr'] as $tr) {
            $output .= $this->render('', $tr);
            foreach ($tr['td'] as $td) {
                $value = $td['attr']['value'];
                unset($td['attr']['value']);
                $output .= $this->render('', $td) . $value . "</" . $td['attr']['type'] . ">";
            }
            $output .= "</tr>\n";
        }
    }

    /***************************** Navigation ******************/
    /**
     * This function takes the menu structure and builds the easyUI HTML markup
     * @param string $output - The running HTML output
     * @param array $prop - properties of the div element
     * @return string - modified $output - HTML formatted EasyUI menu appended to $output
     */
    public function menu(&$output, $prop) {
        msgDebug("\nEntering menu");
        if (empty($prop['data']['child'])) { return; }
        $hideLabel= !empty($prop['hideLabels']) ? $prop['hideLabels'] : false;
        $orient   = !empty($prop['orient']) && $prop['orient']=='v' ? 'v' : 'h';
        if ($orient == 'v') { // vertical
            $size = !empty($prop['size']) ? $prop['size'] : 'small';
            $prop['classes'][] = 'easyui-menu';
            $prop['options']['inline'] = 'true';
        } else { // horizontal
            $size = !empty($prop['size']) ? $prop['size'] : 'large';
        }
        $prop['attr']['type'] = 'div';
        unset($prop['hideLabels'], $prop['size']);
        $output .= $this->render(!empty($prop['attr']['id']) ? $prop['attr']['id'] : '', $prop);
        $output .= $this->menuChild($prop['data']['child'], $size, $orient, $hideLabel);
        $output .= "</div>";
    }

    /**
     * This function takes a menu child structure and builds the easyUI HTML markup, it is recursive for multi-level menus
     * @param array $struc - page structure piece for this menu
     * @param string $size [default: small]- icon size, small or large
     * @param char $orient [default: v] - menu orientation, v - vertical or h - horizontal
     * @param boolean $hideLabel [default: false] - Show or hide textual labels (hide for short format)
     * @return string - HTML menu ready to render
     */
    public function menuChild($struc=[], $size='small', $orient='v', $hideLabel=false) {
        msgDebug("\nEntering menuChild");
        $output    = '';
        $subQueue  = [];
        if (empty($struc)) { return; }
        $structure = sortOrder($struc);
        foreach ($structure as $subid => $submenu) {
            $options = [];
            if (!isset($submenu['security'])|| !empty($submenu['child']))    { $submenu['security'] = 1; }
            if (!empty($submenu['hidden'])  ||  empty($submenu['security'])) { continue; }
            if ( empty($submenu['child'])   && !empty($submenu['icon']) && $orient=='h' && ($hideLabel || !empty($submenu['hideLabel']))) { // just an icon
                $output .= $this->menuIcon($subid, $submenu);
                continue;
            }
            if (!empty($submenu['type']) && $submenu['type'] == 'field') {
                $output .= $this->render($subid, $submenu);
                continue;
            }
            if (empty($submenu['attr']['id'])) { $submenu['attr']['id'] = $subid; }
            if ($orient == 'h') {
                if (empty($submenu['child']))          { $submenu['classes'][] = 'easyui-linkbutton'; }
                elseif ($GLOBALS['myDevice']=='mobile' || empty($submenu['events']['onClick'])) {
                    $submenu['classes'][] = 'easyui-menubutton';
                    $options['plain'] = 'true';
                    $options['hasDownArrow'] = 'false';
                    $options['showEvent'] = "'click'"; }
                else                                   { $submenu['classes'][] = 'easyui-splitbutton'; }
                if (empty($options['plain'])) {$options['plain'] = 'false'; }
                $submenu['styles']['top'] = '0%'; // corrects for offset added by mobile css
                $submenu['styles']['margin-top'] = '0px';
            }
            if (isset($submenu['popup'])) { $submenu['events']['onClick'] = "winOpen('$subid','{$submenu['popup']}');"; }
            if     (isset($submenu['icon']) && $size == 'small') { $options['iconCls']="'icon-{$submenu['icon']}'"; $options['size']="'small'"; }
            elseif (isset($submenu['icon']))                     { $options['iconCls']="'iconL-{$submenu['icon']}'";$options['size']="'large'"; }
            if ($orient == 'h' && !empty($submenu['child'])) { $options['menu'] = "'#sub_{$subid}'"; }
            if (!empty($submenu['disabled'])) { $options['disabled'] = 'true'; }
            $label = !empty($submenu['label']) ? $submenu['label'] : lang($subid);
            // The following line is commmented out as the tooltips from Edge browser cause user issues forcing drop down menus to disappear as you cross the tip
//          $submenu['attr']['title'] = !empty($submenu['tip']) ? $submenu['tip'] : $label;
            $submenu['options'] = $options;
            $badge = !empty($submenu['badge']) ? '<span class="m-badge" style="margin-top:10px;margin-right:10px">'.$submenu['badge'].'</span>' : '';
            if ($orient == 'h') {
                if (isset($submenu['child'])) { $subQueue[] = ['id'=>"sub_{$subid}", 'menu'=>$submenu['child']]; }
                $submenu['attr']['type'] = 'a';
                $output .= "  ".$this->htmlElOpen($subid, $submenu) . $badge . ($hideLabel ? '' : $label) ."</a>\n";
            } else {
                $submenu['attr']['type'] = 'div';
                $output .= "  ".$this->htmlElOpen($subid, $submenu) .($hideLabel ? '' :  "<span>$label</span>") . $badge;
                if (isset($submenu['child'])) { $output .= "\n<div>\n" . $this->menuChild($submenu['child'], 'small', 'v') . "</div>\n"; }
                $output .= " </div>\n";
            }
        }
        if ($orient == 'h') { foreach ($subQueue as $child) { // process the submenu queue
                $output .= "\n".'  <div id="'.$child['id'].'" class="easyui-menu">' . $this->menuChild($child['menu'], 'small', 'v') . "</div>\n";
        } }
        return $output;
    }

    /**
     * Renders an icon without any text (image only)
     * @param string $id - DOM element ID
     * @param array $prop - element properties
     * @return HTML element string
     */
    public function menuIcon($id, $prop) {
        if (empty($prop['size'])) { $prop['size'] = 'large'; } // default to large icons
        $prop['attr']['type'] = 'span';
        switch ($prop['size']) {
            case 'small': $prefix = "icon";  $size = '16px'; break;
            case 'meduim':$prefix = "iconM"; $size = '24px'; break;
            case 'large':
            default:      $prefix = "iconL"; $size = '32px'; break;
        }
        $prop['classes'][]          = "{$prefix}-{$prop['icon']}";
        $prop['styles']['border']   = '0';
        $prop['styles']['display']  = 'inline-block;vertical-align:middle'; // added float:left for mobile lists
        $prop['styles']['height']   = $size;
        $prop['styles']['min-width']= $size;
        $prop['styles']['cursor']   = 'pointer';
        $prop['attr']['title']      = isset($prop['label']) ? $prop['label'] : lang($prop['icon']);
        if (!empty($prop['align']) && $prop['align']=='right') { $prop['styles']['float'] = 'right'; }
//        if ($prop['icon'] == 'help') {
//          $idx = !empty($prop['index']) ? "?section={$prop['index']}" : '';
//          $prop['events']['onClick'] = "var win=winHref('".self::bizunoHelp."$idx'); win.focus();";
//        }
        unset($prop['icon']);
        return $this->render($id, $prop);
    }

    /***************************** Mobile *****************************/
    /**
     * Creates the body list if present
     * @param array $theList - list of menu items to be displayed in the body of the screen
     * @return string
     */
    private function mobileBodyList($theList=[])
    {
        if (empty($theList)) { return ''; }
        $output = '<ul id="list" class="m-list">'."\n";
        $items  = sortOrder($theList);
        foreach ($items as $menuID => $item) {
            $output .= $this->htmlElOpen('', ['events'=>!empty($item['events']) ? $item['events'] : [],'attr'=>['type'=>'li']]);
            unset($item['events']);
            $item['classes']['image'] = "list-image";
            $output .= html5('', $item);
            $output .= '<div class="list-header">'.$item['label'].'</div>';
            if (!empty($item['desc'])) { $output .= '<div>'.$item['desc'].'</div>'; }
            $output .= "</li>\n";
        }
        return $output."</ul>\n";
    }

    /**
     * Determines the type of page to render to build the correct menu
     * @param type $data
     * @return string $type - Choices are home, menu, dash, or target
     */
    private function mobileMenuType()
    {
        if (!getUserCache('profile', 'admin_id', false, 0)) { return 'portal'; } // not logged in
        $path = clean('bizRt', 'filename', 'get');
        if (empty($path) || in_array($path, ['bizuno/main/bizunoHome'])) {
            $menuID = clean('menuID', 'cmd', 'get');
            return !empty($menuID) ? 'menu' : 'home';
        }
        if (empty($path) || in_array($path, ['bizuno/main/dashboard'])) { return 'dash'; }
        return 'target';
    }

    /***************************** Layout ******************/
    /**
     * This function builds the HTML output render a jQuery easyUI accordion feature
     * @param string $output - running string of them HTML output to be add to
     * @param array $data - complete data array containing structure of entire page, only JavaScript part is used to force load from JavaScript data
     * @param string $id - accordion DOM ID
     * @param array $settings - the structure of the accordions (i.e. div structure for each accordion)
     */
    public function layoutAccordion(&$output, $prop) {
        global $viewData;
        $struc = $viewData['accordion'][$prop['key']];
        $this->jsResize[] = "jqBiz('#{$prop['key']}').accordion('resize',{width:jqBiz(this).parent().width()});";
        $struc['attr']['type'] = 'div';
        $struc['classes'][] = 'easyui-accordion';
        if (empty($struc['styles']['width'])) { $struc['styles']['width'] = 'auto'; }
        if (empty($struc['styles']['height'])){ $struc['styles']['height']= 'auto'; }
        $output .= $this->htmlElOpen($prop['key'], $struc);
        $output .= "\n<!-- Begin accordion group {$prop['key']} -->\n";
        $divs = sortOrder($struc['divs']);
        foreach ($divs as $accID => $accContents) {
            $output .= '     <div id="' . $accID . '" title="' . $accContents['label'] . '" style="padding:10px;"';
            if (isset($accContents['options'])) {
                $temp = [];
                foreach ($accContents['options'] as $key => $value) { $temp[] = "$key:$value"; } // was "$key:".encodeType($value);
                $output .= ' data-options="'.implode(',', $temp).'"';
            }
            $output .= "><!-- BOF accordion ".$accID." -->\n";
            unset($accContents['label']);
            $this->buildDiv($output, $accContents);
            $output .= "     </div><!-- EOF accordion ".$accID." -->\n";
        }
        $output .= "  </div>\n";
        if (isset($prop['select'])) {
            $this->jsBody[] = "jqBiz('#{$prop['key']}').accordion('select','{$prop['select']}');";
        }
    }

    /**
     * Handles the layout of an address block. Send defaults to override default configuration settings
     * @param array $output - Output HTML string
     * @param array $props - Element properties
     */
    public function layoutAddress(&$output, $props) {
        global $viewData;
        $defaults= ['type'=>'c','format'=>'short','limit'=>'','suffix' =>'','search'=>false,'props'=>true,'clear'=>true,'copy'=>false,
            'update'=>false,'validate'=>false,'required'=>true,'drop' =>false,'fill'=>'none'];
        $attr    = array_replace($defaults, $props['settings']);
        foreach ($props['fields'] as $field) { $structure[$field] = $viewData['fields'][$field.$attr['suffix']]; }
        $structure['country']['attr']['type'] = 'country'; // triggers the combogrid
        if ($attr['format'] != 'long') { unset($structure['country']['label']); }
        $structure['email']['attr']['size'] = 32; // keep this from overlapping with other divs
        if (!empty($attr['required'])) { foreach (array_keys($structure) as $field) {
            if (getModuleCache('contacts', 'settings', 'address_book', $field)) { $structure[$field]['options']['required'] = true; }
        } }
        // Toolbar
        $toolbar  = [];
        if ($attr['clear']) { $toolbar[] = html5('', ['icon'=>'clear','events'=>['onClick'=>"addressClear('{$attr['suffix']}')"]]); }
        if ($attr['validate'] && getModuleCache('proLgstc', 'properties', 'status')) {
            $toolbar[] = ' '.html5('', ['icon'=>'truck','label'=>lang('validate_address'),'events'=>['onClick'=>"shippingValidate('{$attr['suffix']}');"]]);
        }
        if ($attr['copy']) {
            $src = explode(':', $attr['copy']);
            if (empty($src[1])) { $src = ['_b', '_s']; } // defaults
            $toolbar[] = ' '.html5('',['icon'=>'copy','events'=>['onClick'=>"addressCopy('{$src[0]}', '{$src[1]}')"]]);
        }
        if ($attr['props']) { $toolbar[] = '<span id="spanContactProps'.$attr['suffix'].'" style="display:none">'.html5('contactProps'.$attr['suffix'], ['icon'=>'settings',
            'events' => ['onClick'=>"windowEdit('contacts/main/properties&rID='+jqBiz('#contact_id{$attr['suffix']}').val(), 'winContactProps', '".jsLang('details')."', 1000, 600);"]]).'</span>';
        }
        // Options bar
        $options = [];
        if ($attr['update']) { $options[] = html5('AddUpdate'.$attr['suffix'], ['label'=>lang('add_update'),'attr'=>['type'=>'checkbox']]); }
        if ($attr['drop']) {
            $drop_attr = ['type'=>'checkbox'];
            if (isset($structure['drop_ship']['attr']['checked'])) { $drop_attr['checked'] = 'checked'; }
            $options[] = html5('drop_ship'.$attr['suffix'], ['label'=>lang('drop_ship'), 'attr'=>$drop_attr,
                'events' => ['onChange'=>"jqBiz('#contactDiv{$attr['suffix']}').toggle();"]]);
        }

        $close = $this->buildDivProp($output, $props);
        if (empty($attr['limit']) || $attr['limit']=='a') {
            if (sizeof($toolbar)) { $output .= implode(" ", $toolbar)."<br />"; }
            if (sizeof($options)) { $output .= implode("<br />", $options)."<br />"; }
            $output .= '<div>';
            if ($attr['search'] && (empty($attr['limit']) || $attr['limit']=='a')) {
                $structure['contactSel'] = ['defaults'=>['type'=>$attr['type'],'drop'=>$attr['drop'],'callback'=>"contactsDetail(row.id, '{$attr['suffix']}', '{$attr['fill']}');"],'attr'=>['type'=>'contact']];
                $output .= '<div id="contactDiv'.$attr['suffix'].'"'.($attr['drop']?' style="display:none"':'').'>';
                $output .= html5('contactSel'.$attr['suffix'], $structure['contactSel']).'</div>';
                // Address select (hidden by default)
                $output .= '<div id="addressDiv'.$attr['suffix'].'" style="display:none">'.html5('addressSel'.$attr['suffix'], ['attr'=>['type'=>'text']])."</div>";
                $this->jsBody['addrCombo'.$attr['suffix']] = "var addressVals{$attr['suffix']} = [];
    jqBiz('#addressSel{$attr['suffix']}').combogrid({width:150, panelWidth:750, idField:'id', textField:'primary_name', data:addressVals{$attr['suffix']},
        onSelect: function (id, data){ addressFill(data, '{$attr['suffix']}'); },
        columns:  [[
            {field:'address_id', hidden:true},
            {field:'primary_name',title:'".jsLang('address_book_primary_name')."', width:200},
            {field:'address1',    title:'".jsLang('address_book_address1')    ."', width:100},
            {field:'city',        title:'".jsLang('address_book_city')        ."', width:100},
            {field:'state',       title:'".jsLang('address_book_state')       ."', width: 50},
            {field:'postal_code', title:'".jsLang('address_book_postal_code') ."', width:100},
            {field:'telephone1',  title:'".jsLang('address_book_telephone1')  ."', width:100}]] });";
                // show the address drop down if values are present
    //          if (isset($data['address'][$attr['suffix']])) { $this->jsReady[] = "jqBiz('#addressDiv{$attr['suffix']}').show();"; }
            } else {
                $output .= html5('contactSel'.$attr['suffix'], ['attr'=>['type'=>'hidden']]);
            }
            if ($attr['format'] == 'short') { foreach ($structure as $key => $value) {
                if (!empty($value['label'])) { $structure[$key]['options']['prompt'] = "'".jsLang($value['label'])."'"; }
                unset($structure[$key]['label']);
            } }
            $output .= "</div>\n\n\n";
        }

        if (isset($structure['email'])) { $structure['email'] = array_merge_recursive($structure['email'], ['options'=>['multiline'=>true,'width'=>250,'height'=>60]]); }
        $data = $keys = [];
        if (empty($attr['limit']) || $attr['limit']=='a') { // Address block
            $col1 = ['contact_id','address_id','primary_name','contact','address1','address2','city','state','postal_code','country'];
            foreach ($col1 as $idx) { if (isset($structure[$idx])) {
                $keys[] = $idx.$attr['suffix'];
                $data['fields'][$idx.$attr['suffix']] = $structure[$idx];
            } }
        }
        if (empty($attr['limit']) || $attr['limit']=='c') { // Contact block
            $col2 = ['telephone1','telephone2','telephone3','telephone4','email','website'];
            foreach ($col2 as $idx) { if (isset($structure[$idx])) {
                $keys[] = $idx.$attr['suffix'];
                $data['fields'][$idx.$attr['suffix']] = $structure[$idx];
            } }
        }
        if (!empty($data)) { $output .= $this->layoutFields($data, ['keys'=>$keys]); }
        if ($close) { $output .= "</div>\n"; }
    }

    /**
     * Generates the layout for attachments and file lists
     * @param array $output - structure coming in
     * @param array $prop - element properties
     */
    public function layoutAttach(&$output, $prop) {
        global $viewData, $io;
        $defaults = ['path'=>BIZUNO_DATA,'prefix'=>'','ext'=>[],'delPath'=>'bizuno/main/fileDelete','getPath'=>'bizuno/main/fileDownload','secID'=>'',
            'title'=>lang('attachments'),'noUpload'=>false,'dgName'=>!empty($prop['attr']['id'])?'dg'.$prop['attr']['id']:'dgAttachment'];
        $attr     = array_replace($defaults, $prop['defaults']);
        $path     = $attr['path'].$attr['prefix'];
        $upload_mb= min((int)(ini_get('upload_max_filesize')), (int)(ini_get('post_max_size')), (int)(ini_get('memory_limit')));
        $datagrid = ['id'=>$attr['dgName'],'title'=>$attr['title'].' '.sprintf(lang('max_upload'), $upload_mb),
            'attr'   => ['toolbar'=>"#{$attr['dgName']}Toolbar",'idField'=>'fn'],
            'source' => ['actions'=> ['file_attach'=>['order'=>10,'attr'=>['type'=>'file','name'=>'file_attach']]]],
            'columns'=> [
                'action' => ['order'=>1,'label'=>lang('action'),'attr'=>['width'=>60],
                    'events' => ['formatter'=>"function(value,row,index) { return {$attr['dgName']}Formatter(value,row,index); }"],
                    'actions'=> [
                        'download'=>['order'=>30,'icon'=>'download','events'=>['onClick'=>"jqBiz('#attachIFrame').attr('src','".BIZUNO_AJAX."&bizRt={$attr['getPath']}&pathID=$path&fileID=idTBD');"]],
                        'trash'   =>['order'=>70,'icon'=>'trash',   'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('{$attr['delPath']}&secID={$attr['secID']}','{$attr['dgName']}','{$path}idTBD');"]]]],
                'fn'   => ['order'=>10,'label'=>lang('filename'),'attr'=>['width'=>300,'resizable'=>true]],
                'size' => ['order'=>20,'label'=>lang('size'),    'attr'=>['width'=>100,'resizable'=>true,'align'=>'center']],
                'date' => ['order'=>30,'label'=>lang('date'),    'attr'=>['width'=>100,'resizable'=>true,'align'=>'center']]]];
        if (!empty($attr['url'])) {
            $datagrid['attr']['url']   = $attr['url'];
        } else {
            $rows = $io->fileReadGlob($path, $io->getValidExt('file'));
            $datagrid['events']['data']= json_encode(['total'=>sizeof($rows),'rows'=>$rows]);
        }
        if (!empty($attr['noUpload'])) { unset($datagrid['source']); }
        if ( empty($attr['delPath']))  { unset($datagrid['columns']['action']['actions']['trash']); }
        $viewData['datagrid'][$attr['dgName']] = $datagrid;
        $close = $this->buildDivProp($output, $prop);
        $this->layoutDatagrid($output, ['key'=>$attr['dgName']]);
        if ($close) { $output .= "</div>\n"; }
    }

    /**
     * This function builds the HTML (and JavaScript) content to render a jQuery easyUI datagrid
     * @param array $output - running HTML string to render the page
     * @param string $props - The structure source data to pull from, if key is present then it's viewData, else it's the prop of the div for the datagrid
     * @param array $key - The index in $data to grab the structure to build
     * @return string - HTML formatted EasyUI datagrid appended to $output
     */
    public function layoutDatagrid(&$output, $props, $key=false) {
        global $viewData;
        $output .= $this->htmlElOpen('', array_merge($props, ['attr'=>['type'=>'div']]));
        $prop = $viewData['datagrid'][$props['key']];
        $this->jsReady[] = "jqBiz('#{$prop['id']}').datagrid('resize');";
        $this->jsResize[]= "jqBiz('#{$prop['id']}').datagrid('resize',{width:jqBiz(this).parent().width()});";
        $id = $prop['id'];
        $dgType = (isset($prop['type']) && $prop['type']) ? $prop['type'] : 'datagrid';
        $output .= "<!-- $dgType {$prop['id']} -->\n";
        if (isset($prop['attr']['toolbar'])) { // start the toolbar div
            $output .= '<div id="'.str_replace('#', '', $prop['attr']['toolbar']).'" tabindex="0" style="padding:5px;height:auto">'."\n";
            $output .= "  <div>\n";
            if (isset($prop['source']['filters'])) {
                $prop['source']['filters'] = sortOrder($prop['source']['filters']);
                $temp = $dgGet = [];
                foreach ($prop['source']['filters'] as $key => $value) {
                    if (!empty($value['hidden']) || (!empty($value['attr']['type']) && $value['attr']['type']=='label')) { continue; }
                    $id = isset($value['attr']['id']) ? $value['attr']['id'] : $key; // override id, for dups on multi datagrid page
                    $temp[] = $id.":".dgGetValue($id, !empty($value['attr']['type'])?$value['attr']['type']:'text');
                }
                $this->jsBody[] = "function {$prop['id']}Reload() {\n  jqBiz('#{$prop['id']}').$dgType('load',{".implode(',', $temp)."});\n}";
            }
            if (isset($prop['source']['fields'])) {
                $prop['source']['fields'] = sortOrder($prop['source']['fields']);
                $output .= '<div style="float:right">';
                foreach ($prop['source']['fields'] as $key => $value) {
                    if (!isset($value['hidden']) || !$value['hidden']) { $output .= $this->render($key, $value); }
                }
                $output .= '</div>';
            }
            if (isset($prop['source']['actions'])) {
                $prop['source']['actions'] = sortOrder($prop['source']['actions']);
                // handle the right aligned toolbar elements
                $right = '';
                foreach ($prop['source']['actions'] as $key => $value) {
                    if (isset($value['align']) && $value['align'] == 'right') { $right .= $this->render($key, $value); }
                }
                if ($right) { $output .= '<div style="float:right;">' . $right . "</div>\n"; }
                // now the left aligned
                foreach ($prop['source']['actions'] as $key => $value) {
                    if (empty($value['hidden']) && (!isset($value['align']) || $value['align']=='left')) {
                        $output .= $this->render($key, $value);
                    }
                }
            }
            if (isset($prop['source']['filters'])) {
                if (!empty($prop['source']['filters']['search'])) {
                    $output .= $this->render('search', $prop['source']['filters']['search']);
                    unset($prop['source']['filters']['search']);
                }
                $output .= '<a onClick="' . $prop['id'] . 'Reload();" class="easyui-linkbutton" data-options="iconCls:\'icon-search\'">' . lang('search') . "</a><br />\n";
                foreach ($prop['source']['filters'] as $key => $value) {
                    if (!empty($value['hidden'])) { continue; }
                    $output .= $this->render($key, $value);
                }
            }
            $output .= "  </div>\n";
            $output .= "</div>\n";
        }
        if (isset($prop['columns']) && is_array($prop['columns'])) { // build the formatter for the action column
            $prop['columns'] = sortOrder($prop['columns']);
            if (!empty($prop['columns']['action']['actions'])) {
                $actions = sortOrder($prop['columns']['action']['actions']);
                $jsBody = "  var text = '';";
                foreach ($actions as $id => $event) {
                    if (!isset($event['hidden'])) { $event['hidden'] = false; }
                    if (!$event['hidden']) {
                        if (isset($event['display'])) { $jsBody .= "  if ({$event['display']})"; }
                        unset($event['size']); // make all icons large
                        $temp = $this->render('', $event) . "&nbsp;";
                        $jsBody .= "  text += '" . str_replace(["\n", "\r", "'"], ["", "", "\'"], $temp) . "';\n";
                    }
                }
                $jsBody .= "  text = text.replace(/indexTBD/g, index);\n";
                if (isset($prop['attr']['idField'])) { // for sending db ID's versus row index ID's
                    $jsBody .= "  text = text.replace(/idTBD/g, row.{$prop['attr']['idField']});\n";
                }
                if (isset($prop['attr']['xtraField'])) { // for replacing row.values
                    foreach ($prop['attr']['xtraField'] as $rField) {
                        $jsBody .= "  text = text.replace(/{$rField['key']}/g, row.{$rField['value']});\n";
                    }
                }
                $btnMore  = trim($this->render("more_{$prop['id']}_indexTBD", ['icon'=>'more','size'=>'small','label'=>lang('more'),'events'=>['onClick'=>"myMenu{$prop['id']}(event, indexTBD)"],'attr'=>['type'=>'button']]));
                $funcMore = "function {$prop['id']}Formatter(value, row, index) {\n  var text='$btnMore';\n";
                $funcMore.= "  text = text.replace(/indexTBD/g, index);\n  return text;\n}\n";
                $this->jsBody[] = $funcMore."function myMenu{$prop['id']}(e, index) {
    e.preventDefault();
    jqBiz('#{$prop['id']}').datagrid('unselectAll');
    jqBiz('#{$prop['id']}').datagrid('selectRow', index);
    var row = jqBiz('#{$prop['id']}').datagrid('getRows')[index];
    if (typeof row == 'undefined') {  }
    jqBiz('#tmenu').remove();
    var tmenu = jqBiz('<div id=\"tmenu\"></div>').appendTo('body');
    $jsBody
    jqBiz('<div />').html(text).appendTo(tmenu);
    tmenu.menu({ minWidth:30,itemHeight:44,onClick:function(item) {  } });
    jqBiz('#more_{$prop['id']}_'+index).removeClass('icon-more');
    jqBiz('#more_{$prop['id']}_'+index).menubutton({ iconCls: 'icon-more', menu:'#tmenu',hasDownArrow: false });
    jqBiz('#tmenu').menu('show',{left:e.pageX, top:e.pageY} );
}";
                $prop['events']['onRowContextMenu'] = "function (e, index, row) { myMenu{$prop['id']}(e, index); }";
            }
        }
        $output .= '<table id="' . $prop['id'] . '"';
        if (isset($prop['title'])) { $output .= ' title="' . $prop['title'] . '"'; }
        $output .= "></table>";
        if (isset($prop['footnotes'])) {
            $output .= '<b>' . lang('notes') . ":</b><br />\n";
            foreach ($prop['footnotes'] as $note) { $output .= $note . "<br />\n"; }
        }
        if (isset($prop['tip'])) { $output .= "<div>\n  " . $prop['tip'] . "\n</div>\n"; }
        $js = "jqBiz('#{$prop['id']}').$dgType({\n";
        $options = [];
        if ( empty($prop['attr']['width'])) { $prop['attr']['width'] = '100%'; }
        if (!empty($prop['rows'])) { $options[] = "pageSize:{$prop['rows']}"; }
        if (!empty($prop['attr'])) { foreach ($prop['attr']   as $key => $value) { $options[] = "  $key:" . encodeType($value); } }
        if (isset($prop['events'])){ foreach ($prop['events'] as $key => $value) { $options[] = " $key:$value"; } }
        // build the columns
        $cols = [];
        foreach ($prop['columns'] as $col => $settings) {
            $settings['attr']['field'] = $col;
            $settings['attr']['title'] = isset($settings['label']) ? $settings['label'] : $col;
            $temp = [];
            foreach ($settings['attr'] as $key => $value) { $temp[] = "$key:" . encodeType($value); }
            if (!empty($settings['events'])) { // removed condition:  && empty($settings['attr']['hidden']) to allow shipping ounces
                foreach ($settings['events'] as $key => $value) { $temp[] = "$key:$value"; }
            }
            $cols[] = "    { " . implode(",", $temp) . " }";
        }
        $options[] = "  columns:[[\n" . implode(",\n", $cols) . "\n]]";
        $js .= implode(",\n", $options) . "\n});";
        $this->jsBody[] = $js;
        $output .= "</div><!-- EOF Datagrid -->\n";
    }

    /**
     *
     * @param type $menuID
     */
    public function layoutDesktop($menuID) {
        $bizID = getUserCache('profile', 'biz_id');
        msgDebug("\nEntering layoutDesktop with menuID = $menuID and bizID = $bizID");
        $logoPath= getModuleCache('bizuno', 'settings', 'company', 'logo');
        $src     = $logoPath ? BIZBOOKS_URL_FS."&src=$bizID/images/$logoPath" : BIZUNO_LOGO;
        $portal  = explode('.', $_SERVER['SERVER_ADDR']);
        $version = MODULE_BIZUNO_VERSION."-{$portal[3]}-".getUserCache('profile', 'language')."-".getDefaultCurrency();
        if (!empty($bizID) && empty($GLOBALS['bizuno_not_installed'])) {
            $title = getModuleCache('bizuno', 'settings', 'company', 'primary_name');
            if (empty($title)) { $title = portalGetBizIDVal($bizID, 'title'); }
            $company = $title.' - '.lang('period').': '.getModuleCache('phreebooks', 'fy', 'period').' | '.$version;
            $company.= ' - '.getModuleCache('bizuno', 'properties', 'title').' | '.lang('copyright').' &copy;'.biz_date('Y').' <a href="http://www.PhreeSoft.com" target="_blank">PhreeSoft&trade;</a>';
            if ($GLOBALS['bizunoModule'] <> 'bizuno') { $company .= '-'.$GLOBALS['bizunoModule'].' '.getModuleCache($GLOBALS['bizunoModule'], 'properties', 'status'); }
            $menus   = dbGetRoleMenu();
        } else {
            $company = $version.' - '.lang('copyright').' &copy;'.biz_date('Y').' <a href="http://www.PhreeSoft.com" target="_blank">PhreeSoft&trade;</a>';
            $menus = [];
        }
        $data    = ['type'=>'page',
            'header'  => ['classes'=>['m-toolbar'],'type'=>'divs','divs'=>[
                'left'  => ['order'=>10,'type'=>'fields','classes'=>['m-left'],'keys'=>['logo']],
                'center'=> ['order'=>20,'type'=>'menu',  'classes'=>['menuHide','m-title'],'styles'=>['display'=>'none'],'data'=>$menus['menuBar'],'attr'=>['id'=>'rootMenu']],
                'right' => ['order'=>30,'type'=>'menu',  'classes'=>['menuHide','m-right'],'styles'=>['display'=>'none'],'data'=>$menus['quickBar']]]],
//          'divs'    => [], // body supplied by the module detail
            'footer'  => ['classes'=>['m-toolbar'],'type'=>'divs','divs'=>[
                'left'  => ['order'=>10,'type'=>'html','styles'=>['font-size'=>'10px'],'html'=>$company]]],
            'fields'  => ['logo'=>['label'=>getModuleCache('bizuno','properties','title'),'styles'=>['cursor'=>'pointer'],'events'=>['onClick'=>"hrefClick('');"],'attr'=>['type'=>'img','src'=>$src,'height'=>48]]],
            'jsReady' => ['initPage'=>"jqBiz('.menuHide').css('display', 'inline-block'); bizMenuResize();"],
            'jsResize'=> ['rootMenu'=>"bizMenuResize();"]];
        if (!empty($menuID) && $menuID<>'home') { // show the submenu before the dashboards, except mobile
            $height = !empty($menuID) && $menuID<>'home' ? 96 : 32;
            $data['divs']['subMenu'] = ['order'=> 6,'type'=>'menu','region'=>'top','height'=>$height,'classes'=>['menuHide'],'styles'=>['display'=>'none','clear'=>'both'],'data'=>viewSubMenu(),'attr'=>['id'=>'bizSubMenu']];
        }
        if (defined('BIZUNO_MY_FOOTER') && !biz_validate_user()) {
//          $data['divs']['footer']['divs']['myFooter'] = ['order'=>10, 'type'=>'html', 'html'=>BIZUNO_MY_FOOTER];
        }
        return $data;
    }

    /**
     * Build the view for a list of fields
     * @param array $prop
     * @return string - HTML markup
     */
    public function layoutFields($data=[], $prop=[]) {
        $tmp = [];
        if (empty($prop['keys'])) {
            msgDebug("\nCalled html.php layoutFields with no fields defined!");
            return '';
        }
        foreach ($prop['keys'] as $key) { if (!empty($data['fields'][$key])) { $tmp[$key] = $data['fields'][$key]; } }
        $fields = sortOrder($tmp);
        $output = '';
        $close = $this->buildDivProp($output, $prop);
        foreach ($fields as $key => $props) {
            if (!isset($props['break'])) { $props['break'] = true; }
            $id = !empty($props['attr']['id']) ? $props['attr']['id'] : $key;
            $output .= $this->render($id, $props);
        }
        if ($close) { $output .= "</div>\n"; }
        return $output;
    }

    public function layoutHTML($prop) {
        $output = '';
        $close  = $this->buildDivProp($output, $prop);
        $output.= !empty($prop['html']) ? $prop['html']."\n" : '';
        if ($close) { $output .= "</div>\n"; }
        return $output;
    }

    public function layoutList($layout, $props) {
        $format = !empty($props['ui']) && $props['ui']=='none' ? 'none' : '';
        $output = '';
        $close  = $this->buildDivProp($output, $props);
        if ($format == 'none') {
            $output .= "<p>";
        } else {
            $props['attr']['type'] = 'ul';
            $props['classes'][] = 'easyui-datalist';
            $props['options']['nowrap'] = 'false';
            $output .= "\n<!-- BOF list -->\n".$this->htmlElOpen(!empty($props['id']) ? $props['id'] : '', $props)."\n";
        }
        foreach ($layout['lists'][$props['key']] as $fld => $li) {
            $fldID = !empty($li['attr']['id']) ? $li['attr']['id'] : $fld;
            if ($format=='none') {
                $output .= (is_array($li) ? $this->render($fldID, $li) : $li);
                if (!isset($li['break']) || $li['break']) { $output .= "</p>\n<p>"; }
            } else {
                $output .= "<li>". (is_array($li) ? $this->render($fldID, $li) : $li) . "</li>\n";
            }
        }
        if ($format=='none') { $output .= "</p>\n"; }
        else                 { $output .= "</ul><!-- EOF List -->\n"; }
        if ($close) { $output .= "</div>\n"; }
        return $output;
    }

    /**
     * Special case for mobile to generate main menu of only one level and off of a single home icon
     * @return type
     */
    public function layoutMenuLeft($type='home', $menuID='', $menuSel=[])
    {
        msgDebug("\nEntering layoutMenuLeft working with type = $type and menuID = ".print_r($menuID, true));
        switch ($type) {
            case 'add':   return html5('', ['order'=>10,'icon'=>'add','options'=>['menuAlign'=>"'right'"],
                'classes'=>['easyui-linkbutton'],'events'=>['onClick'=>"hrefClick('".BIZUNO_HOME."&bizRt=bizuno/dashboard/manager&menuID=$menuID');"]]);
            case 'back':  return ['child'=>['back'=>['order'=>50,'icon'=>'back','events'=>['onClick'=>"jqBiz.mobile.back();"]]]];
            case 'close': return html5('', ['order'=>10,'icon'=>'close','options'=>['menuAlign'=>"'left'"],
                'classes'=>['easyui-linkbutton'],'events'=>['onClick'=>"jqBiz.mobile.back();"]]);
            default:
            case 'home':
                $menus= [];
                if (!empty($menuSel)) { foreach ($menuSel['child'] as $key => $child) {
                    unset($child['child']);
                    $menus[$key] = $child;
                } }
                $menus['home'] = ['order'=>0,'icon'=>'home','label'=>lang('home'),'events'=>['onClick'=>"hrefClick('');"]];
                return ['child'=>['home'=>['order'=>50,'icon'=>'home','child'=>$menus]]];
            case 'menu':
                foreach ($menuSel['child'][$menuID]['child'] as $key => $child) {
                    unset($child['child']);
                    $menus[$key] = $child;
                }
                $menus['home'] = ['order'=>0,'icon'=>'home','label'=>lang('home'),'events'=>['onClick'=>"hrefClick('');"]];
                return ['child'=>['home'=>['order'=>50,'icon'=>'sales','child'=>$menus]]];
            case 'more':  return html5('', ['order'=>10,'icon'=>'more','options'=>['hasDownArrow'=>'false','menu'=>"'#more$menuID'",'menuAlign'=>"'right'"],
                'classes'=>['easyui-menubutton']]);
        }
    }

    /**
     *
     * @param type $menuID
     */
    public function layoutMobile($menuID='')
    {
        $pgType= $this->mobileMenuType();
        msgDebug("\nEntering layoutMobile with type = $pgType and menuID = $menuID");
        $header= [];
        $menus = dbGetRoleMenu();
        $title = getModuleCache('bizuno', 'settings', 'company', 'primary_name');
        switch($pgType) {
            case 'home':   $header = ['title'=>$title,             'left'=>'home','right'=>''];     break;
            case 'menu':   $header = ['title'=>lang($menuID),      'left'=>'menu','right'=>'more']; break;
            case 'dash': // Dashboard manager screen
                $title  = in_array($menuID, ['settings','home']) ? lang('bizuno_company') : $menus['menuBar']['child'][$menuID]['label'];
                $header = ['title'=>$title,'left'=>'back','right'=>'add'];                          break;
            case 'target': $header = ['title'=>$title,             'left'=>'back','right'=>'more']; break;
            case 'portal': $header = ['title'=>'Welcome to Bizuno','left'=>'',    'right'=>''];     break;
        }
        unset($menus['quickBar']['child']['settings']['child']['newTab']); // no new tab for mobile
        $data  = ['type'=>'page',
            'header'  => ['classes'=>['m-toolbar'],'type'=>'divs','divs'=>[
              'left'  => ['order'=>10,'type'=>'menu','size'=>'small','hideLabels'=>true,'classes'=>['menuHide','m-left'],'options'=>['plain'=>'true'],
                  'styles'=>['display'=>'none'],'data'=>$this->layoutMenuLeft($header['left'], $menuID, $menus['menuBar']),'attr'=>['id'=>'rootMenu']],
              'center'=> ['order'=>20,'type'=>'html','classes'=>['m-title'],'html'=>$header['title']],
              'right' => ['order'=>30,'type'=>'menu','size'=>'small','hideLabels'=>true,'classes'=>['menuHide','m-right'],'options'=>['plain'=>'true'],
                  'styles'=>['display'=>'none'],'data'=>$menus['quickBar'],'attr'=>['id'=>'quickMenu']]]],
//          'divs'  => [], // body supplied by the module detail
            'jsReady' => ['initPage'=>"jqBiz('.menuHide').css('display','inline-block'); window.onorientationchange = function() { window.location.reload(); };"]];
        $this->layoutMobileFooter($data, $pgType, $menuID, $menus);
        return $data;
    }

    private function layoutMobileFooter(&$data, $page='', $menuID='', $menus)
    {
        switch ($page) {
            case 'menu': $menu = $menus['menuBar']['child'][$menuID]['child']; break;
            case 'home': $menu = $menus['menuBar']['child'];                   break;
            default: return;
        }
        $cnt   = 1;
        $fields= [];
        $theList = sortOrder($menu);
        foreach ($theList as $key => $child) {
            $data['fields'][$key] = ['order'=>$child['order'],'break'=>false,'attr'=>['type'=>'a','value'=>$child['label']],'classes'=>['easyui-linkbutton'],
                'options'=>['iconCls'=>"'iconL-{$child['icon']}'",'iconAlign'=>"'top'",'size'=>"'large'",'plain'=>'true'],'events'=>['onClick'=>$child['events']['onClick']]];
                $fields[] = $key;
                $cnt++;
                if ($cnt > 4) { break; } // limit results to first 4 menus
        }
        $data['footer'] = ['classes'=>['m-toolbar'],'type'=>'divs','divs'=>[
            'center' => ['order'=>20,'type'=>'fields','classes'=>['m-buttongroup','m-buttongroup-justified'],'styles'=>['width'=>'100%'],'keys'=>$fields]]];
    }

    /**
     * This function builds the HTML output render a jQuery easyUI panel feature
     * @param string $output - running string of them HTML output to be add to
     * @param array $data - complete data array containing structure of entire page, only JavaScript part is used to force load from JavaScript data
     * @param string $id - accordion DOM ID
     * @param array $settings - the structure of the accordions (i.e. div structure for each accordion)
     */
    public function layoutPanel(&$output, $prop)
    {
        global $viewData;
        if (empty($prop['key'])) { msgAdd("Entered layoutPanel with no key! Bailing."); }
        $data = $viewData['panels'][$prop['key']];
        msgDebug("\nEntering layoutPanel with prop = ".print_r($prop, true));
        msgDebug("\nEntering layoutPanel with panel data = ".print_r($data, true));
        if (!empty($data['divs'])) { // new way
            // build the container div
            $data['classes'][] = 'easyui-panel';
            if (empty($data['id']))    { $data['id']=''; }
            if (empty($data['title'])) { $data['title']=''; }
            $output .= '<div '.$this->addAttrs($prop).">\n".'  <div id="'.$data['id'].'" title="'.$data['title'].'"'.$this->addAttrs($data);
            $opts = [];
            if (!empty($data['opts']['closable']))   { $opts[] = "closable:true"; }
            if (!empty($data['opts']['collapsible'])){ $opts[] = "collapsible:true"; }
            if (!empty($data['opts']['collapsed']))  { $opts[] = "collapsed:true"; }
            if (!empty($data['opts']['minimizable'])){ $opts[] = "minimizable:true"; }
            if (!empty($data['opts']['maximizable'])){ $opts[] = "maximizable:true"; }
            if (!empty($data['opts']['icon']))       { $opts[] = "iconCls:'icon-{$data['opts']['icon']}'"; }
            $output .= ' data-options="'.implode(',', $opts).'"';
            $output .= '>';
            foreach ($data['divs'] as $div) { $this->buildDiv($output, $div); }
            $output .= '  </div>';
            // close container
            $output .= '</div>';
        } else { // DEPRECATED - OLD WAY
            $panel = $viewData['panels'][$prop['key']]; // get the panel
            $pProp['attr']['type'] = 'div'; // panel
            if (!empty($panel['id']))         { $pProp['attr']['id'] = $panel['id']; } // DEPRECATED, Old way
            if (!empty($panel['attr']['id'])) { $pProp['attr']['id'] = $panel['attr']['id']; }
            $pProp['classes'][]= "easyui-panel";
            $pProp['options']  = !empty($panel['options']) ? $panel['options'] : [];
            if (!empty($panel['label'])) { $pProp['options']['title'] = "'".addslashes($panel['label'])."'"; }
            else                         { $pProp['options']['border']= 'false'; }
            unset($panel['label'], $panel['options'], $panel['attr']['id']);
            $close   = $this->buildDivProp($output, $prop);
            $output .= $this->htmlElOpen('', $pProp); // easyui panel
            $this->buildDiv($output, $panel);
            $output .= "</div>\n";
            if ($close) { $output .= "</div>\n"; }
        }
    }

     public function layoutPayment(&$output, $prop) {
        global $viewData;
        $viewDataValues= [];
        $dispFirst = $viewData['fields']['selMethod']['attr']['value'] = $viewData['fields']['method_code']['attr']['value'];

        if (isset($prop['settings']['items'])) { foreach ($prop['settings']['items'] as $row) { // fill in the data if available
            $props = explode(";", $row['description']);
            foreach ($props as $val) {
                $tmp = explode(":", $val);
                $viewDataValues[$tmp[0]] = isset($tmp[1]) ? $tmp[1] : '';
            }
            if (empty($row['item_ref_id'])) { continue; }
            $txID = dbGetValue(BIZUNO_DB_PREFIX."journal_item", array('description','trans_code'), "ref_id='{$prop['settings']['items'][0]['item_ref_id']}' AND gl_type='ttl'");
            $props1 = !empty($txID['description']) ? explode(";", $txID['description']) : [];
            foreach ($props1 as $val) {
                $tmp = explode(":", $val);
                $viewDataValues[$tmp[0]] = isset($tmp[1]) ? $tmp[1] : '';
            }
            $viewDataValues['id']        = $row['item_ref_id'];
            $viewDataValues['trans_code']= !empty($txID['trans_code']) ? $txID['trans_code'] : '';
            $viewDataValues['total']     = !empty($row['total']) ? $row['total'] : 0;
        } }
        // set the pull down for which method, onChange execute javascript function to load defaults
        $close  = $this->buildDivProp($output, $props);
        $output .= html5('method_code', $viewData['fields']['selMethod']);
        $methods = sortOrder(getModuleCache('payment', 'methods'));
        foreach ($methods as $method => $settings) {
            if (!empty($settings['status'])) { // load the div for each method
                $fqcn  = "\\bizuno\\$method";
                if (!bizAutoLoad($settings['path']."$method.php", $fqcn)) {
                    msgAdd("I cannot find the method $method to load! Skipping.");
                    continue;
                }
                if (!$dispFirst) { $dispFirst = $method; }
                $style = $dispFirst == $method ? '' : ' style="display:none;"';
                $output .= '<div id="div_'.$method.'" class="layout-expand-over"'.$style.'>'."\n";
                $pmtSet= getModuleCache('payment','methods',$method,'settings');
                $temp  = new $fqcn($pmtSet);
                $output .= $temp->render($viewData, $viewDataValues, $dispFirst);
                $output .= "</div>\n";
            }
        }
        $this->jsReady[] = "payment_$dispFirst();"; // force loading of defaults for displayed payment method
        if ($close) { $output .= "</div>\n"; }
    }

    /**
     * This function generates the tabs pulled from the current structure, position: $data['tabs'][$idx]
     * @param array $output - running HTML string to render the page
     * @param string $prop - The structure source data to pull from
     * @param array $idx - The index in $prop to grab the structure to build
     * @return string - HTML formatted EasyUI tabs appended to $output
     */
    public function layoutTab(&$output, $prop) {
        global $viewData;
        $struc = $viewData['tabs'][$prop['key']];
        $this->jsResize[] = "jqBiz('#{$prop['key']}').tabs('resize');";
        if (isset($prop['focus'])) {
            $indices = array_keys($struc['divs']);
            foreach ($indices as $key => $tabID) { if ($prop['focus'] == $tabID) { $struc['options']['selected'] = $key; } }
        }
        $struc['attr']['type'] = 'div';
        $struc['classes']['ui']= "easyui-tabs";
        $struc['options']['onSelect'] = "function (title, idx) { setTimeout(function () { resizeEverything(); }, 250); }"; // because this fires too quickly
        $tabs = sortOrder($struc['divs']);
        unset($prop['focus'], $struc['divs']);
        $output .= $this->htmlElOpen($prop['key'], $struc)."\n<!-- Begin tab group {$prop['key']} -->\n";
        foreach ((array)$tabs as $tabID => $tabDiv) {
            $tabDiv['attr']['id'] = $tabID;
            $tabDiv['options']['title'] = !empty($tabDiv['label']) ? "'".addslashes($tabDiv['label'])."'" : "'$tabID'";
            $tabDiv['classes']['display']= 'menuHide';
            $tabDiv['styles']['padding'] = '5px';
            if (!empty($tabDiv['icon'])) { $tabDiv['attr']['iconCls'] = "icon-{$tabDiv['icon']}"; }
            $output .= "<!-- Begin tab $tabID -->\n";
            unset($tabDiv['label']); // clear the label or it will be create a duplicate
            $this->buildDiv($output, $tabDiv);
            $output .= "<!-- End tab $tabID -->\n";
        }
        $output .= "</div><!-- End tab group {$prop['key']} -->\n";
        $this->jsReady[] = "jqBiz('.menuHide').show();";
    }

    public function layoutTable(&$output, $prop) {
        $close   = $this->buildDivProp($output, $prop);
        $output .= $this->htmlElOpen('', $prop) . "\n";
        if (isset($prop['thead'])) {
            $output .= $this->layoutTableRows($prop['thead']) . "</thead>\n";
        }
        if (isset($prop['tbody'])) {
            $output .= $this->layoutTableRows($prop['tbody']) . "</tbody>\n";
        }
        if (isset($prop['tfoot'])) {
            $output .= $this->layoutTableRows($prop['tfoot']) . "</tfoot>\n";
        }
        $output .= "</table><!-- End table {$prop['attr']['id']} -->\n";
        if ($close) { $output .= "</div>\n"; }
    }

    private function layoutTableRows($region) {
        $output = $this->htmlElOpen('', $region) . "\n";
        foreach ($region['tr'] as $tr) {
            $output .= $this->htmlElOpen('', $tr);
            foreach ($tr['td'] as $td) {
                $value = $td['attr']['value'];
                unset($td['attr']['value']);
                $output .= $this->htmlElOpen('', $td) . $value . "</{$td['attr']['type']}>\n";
            }
            $output .= "</tr>\n";
        }
        return $output;
    }

    /**
     * This function generates a HTML toolbar pulled from the current structure
     * @param array $output - running HTML string to render the page
     * @param array $id - The index in $prop to grab the structure to build
     * @param string $prop - The structure source data to pull from
     * @return string - HTML formatted EasyUI toolbar appended to $output
     */
    public function layoutToolbar(&$output, $prop) {
        if (!empty($prop['hidden'])) { return; } // toolbar is hidden
        if (is_array($prop['icons']) && !empty($prop['icons'])) { foreach ($prop['icons'] as $name => $struc) {
            if (!isset($struc['type'])) { $prop['icons'][$name]['type'] = 'icon'; }
            if (!isset($struc['icon']) && $prop['icons'][$name]['type'] == 'icon') { $prop['icons'][$name]['icon'] = $name; }
        } }
        $prop['data']['child'] = $prop['icons'];
        unset($prop['icons']);
        $prop['size'] = 'large';
        $prop['region'] = 'center';
        $this->menu($output, $prop);
    }

    /**
     * This functions builds the HTML for a jQuery easyUI tree
     * @param array $output - running HTML string to render the page
     * @param string $prop - The structure source data to pull from
     * @param array $idx - The index in $prop to grab the structure to build
     * @return string - HTML formatted EasyUI tree appended to $output
     */
    public function layoutTree(&$output, $prop) {
        $this->jsResize[] = "jqBiz('#{$prop['id']}').tree('resize',{width:jqBiz(this).parent().width()});";
        $temp = [];
        $output .= '<ul id="' . $prop['id'] . '"></ul>' . "\n";
        if (isset($prop['menu'])) {
            $output .= "<div";
            foreach ($prop['menu']['attr'] as $key => $value) { $output .= ' ' . $key . '="' . str_replace('"', '\"', $value) . '"'; }
            $output .= ">\n";
            foreach ($prop['menu']['actions'] as $key => $value) {
                $output .= '  <div id="' . $key . '"';
                foreach ($value['attr'] as $key => $val) { $output .= ' ' . $key . '="' . str_replace('"', '\"', $val) . '"'; }
                $output .= ">" . (isset($value['label']) ? $value['label'] : '') . "</div>\n";
            }
            $output .= "</div>\n";
        }
        if (isset($prop['footnotes'])) {
            $output .= '<b>' . lang('notes') . ":</b><br />\n";
            foreach ($prop['footnotes'] as $note) { $output .= $note . "\n"; }
        }
        foreach ($prop['attr'] as $key => $value) {
            $val = is_bool($value) ? ($value ? 'true' : 'false') : "'$value'";
            $temp[] = "$key: $val";
        }
        if (isset($prop['events'])) {
            foreach ($prop['events'] as $key => $value) { $temp[] = "      $key: $value"; }
        }
        $this->jsBody[] = "jqBiz('#".$prop['id']."').tree({\n".implode(",\n", $temp)."\n});\n";
    }

    /***************************** Forms ******************/

    public function input($id, $prop) {
        $this->addID($id, $prop);
        $field = '<input';
        if (isset($prop['attr']['value'])) {
            $value = isset($prop['format']) ? viewFormat($prop['attr']['value'], $prop['format']) : $prop['attr']['value'];
            $field .= ' value="' . str_replace('"', '&quot;', $value) . '"';
            unset($prop['attr']['value']);
        }
        if (!empty($prop['js']))     { $this->jsBody[] = $prop['js']; } // old way
        if (!empty($prop['jsBody'])) { $this->jsBody[] = $prop['jsBody']; } // new way
        $output = $this->addLabelFirst($id, $prop) . $field . $this->addAttrs($prop) . " />" . $this->addLabelLast($id, $prop);
        if (!empty($prop['tip'])) { $output .= $this->addToolTip($id, $prop['tip']); }
        $hidden = isset($prop['attr']['type']) && $prop['attr']['type']=='hidden' ? true : false;
        return $output . (!empty($prop['break']) && !$hidden ? '<br />' : '');
    }

    public function inputButton($id, $prop) {
        $this->addID($id, $prop);
        $prop['attr']['type'] = 'a';
        $prop['classes'][] = 'easyui-linkbutton';
//      if (!isset($prop['attr']['href'])) { $prop['attr']['href'] = '#'; } // causes jQuery error and halts script
        return $this->htmlElBoth($id, $prop);
    }

    public function inputCheckbox($id, $prop) {
        if (empty($prop['attr']['checked'])) { unset($prop['attr']['checked']); }
        $prop['classes'][] = 'easyui-switchbutton';
        $prop['options']['value'] = !empty($prop['attr']['value']) ? "'".$prop['attr']['value']."'" : 1;
        $prop['options']['onText']  = "'".jsLang('yes')."'";
        $prop['options']['offText'] = "'".jsLang('no')."'";
        $prop['attr']['type'] = 'text';
        unset($prop['attr']['value']);
        $this->mapEvents($prop);
        return $this->input($id, $prop);
    }

    public function inputColor($id, $prop) {
        $prop['classes'][] = 'easyui-color';
        return $this->input($id, $prop);
    }

    public function inputContact($id, $prop) {
        $defs = ['type'=>'c','value'=>0,'suffix'=>'','store'=>0,'drop'=>false,'fill'=>false,'data'=>false,'callback'=>"contactsDetail(row.id, '', false);"];
        $attr = array_merge($defs, $prop['defaults']);
        $url  = "'".BIZUNO_AJAX."&bizRt=contacts/main/managerRows&clr=1";
        $url .= "&type=" .(!empty($attr['drop'])  ? 'c' : $attr['type']);
        $url .= "&store=".(!empty($attr['store']) ? '1' : '0');
        $url .= "'";
        $prop['classes'][]               = 'easyui-combogrid';
        $prop['options']['width']        = "250,panelWidth:750,delay:900,idField:'id',textField:'primary_name',mode:'remote',iconCls:'icon-search',hasDownArrow:false,selectOnNavigation:false";
        $prop['options']['url']          = $url;
        $prop['options']['onBeforeLoad'] = "function (param) { var newValue=jqBiz('#$id').combogrid('getValue'); if (newValue.length < 3) return false; }";
        if (!empty($prop['attr']['value']) && empty($attr['data'])) {
            $selText = dbGetValue(BIZUNO_DB_PREFIX.'address_book', 'primary_name', "ref_id='{$prop['attr']['value']}'");
            $this->jsReady[] = "jqBiz('#$id').combogrid({data:".json_encode([['id'=>$prop['attr']['value'], 'primary_name'=>$selText]])."});";
        }
        if (!empty($attr['data']))     { $prop['options']['data']      = $attr['data']; }
        if (!empty($attr['callback'])) { $prop['options']['onClickRow']= "function (idx, row) { {$attr['callback']} }"; }
        $prop['options']['columns']      = "[[{field:'id', hidden:true},
    {field:'short_name',  title:'".jsLang('contacts_short_name')."', width:100},
    {field:'type',        hidden:".(strlen($attr['type'])>1?'false':'true').",title:'".jsLang('contacts_type')."', width:100},
    {field:'primary_name',title:'".jsLang('address_book_primary_name')."', width:200},
    {field:'address1',    title:'".jsLang('address_book_address1')."', width:100},
    {field:'city',        title:'".jsLang('address_book_city')."', width:100},
    {field:'state',       title:'".jsLang('address_book_state')."', width: 50},
    {field:'postal_code', title:'".jsLang('address_book_postal_code')."', width:100},
    {field:'telephone1',  title:'".jsLang('address_book_telephone1')."', width:100}]]";
        unset($prop['attr']['type'], $prop['defaults']);
        return $this->input($id, $prop);
    }

    public function inputCountry($id, $prop) {
        msgDebug("\nid = $id and prop = ".print_r($prop, true));
        $value = !empty($prop['attr']['value']) ? $prop['attr']['value'] : getModuleCache('bizuno','settings','company','country','USA');
        $prop['classes'] = ['easyui-combogrid'];
        $prop['options']['data']      = "bizDefaults.countries";
        $prop['options']['width']     = 150;
        $prop['options']['panelWidth']= 300;
        $prop['options']['value']     =  "'$value'" ;
        $prop['options']['idField']   = "'iso3'";
        $prop['options']['textField'] = "'title'";
        $prop['options']['columns']   = "[[{field:'iso3',title:'".jsLang('code')."',width:60},{field:'title',title:'".jsLang('title')."',width:200}]]";
        return $this->input($id, $prop);
    }

    public function inputCurrency($id, $prop) {
        $cur = !empty($GLOBALS['bizunoCurrency']) ? $GLOBALS['bizunoCurrency'] : getDefaultCurrency();
        $iso = getModuleCache('phreebooks', 'currency', 'iso', $cur);
        if (empty($iso)) { $iso = getModuleCache('phreebooks', 'currency', 'iso', getDefaultCurrency()); }
        $prop['classes'][] = 'easyui-numberbox';
        $prop['options']['decimalSeparator']= "'".addslashes($iso['dec_pt'])."'";
        $prop['options']['groupSeparator']  = "'".addslashes($iso['sep'])."'";
        $prop['options']['precision']       = intval($iso['dec_len']);
        if (!empty($iso['prefix'])) { $prop['options']['prefix'] = "'" .addslashes($iso['prefix'])." '"; }
        if (!empty($iso['suffix'])) { $prop['options']['suffix'] = "' ".addslashes($iso['suffix'])."'"; }
        $prop['styles']['text-align'] = 'right';
        if (empty($prop['options']['width'])) { $prop['options']['width'] = 125; }
        unset($prop['attr']['type'], $prop['attr']['size'], $prop['format']);
        $this->mapEvents($prop);
        return $this->input($id, $prop);
    }

    public function inputDate($id, $prop) {
        if (empty($prop['styles']['width'])) { $prop['styles']['width']  = '150px'; }
        $prop['classes'][] = 'easyui-datebox';
        $prop['attr']['type'] = 'text'; // needed to turn off browser takeover of date box (Chrome)
        if (!empty($prop['attr']['value'])) {
            $prop['options']['value'] = "'".viewDate($prop['attr']['value'])."'";
            unset($prop['attr']['value']);
        }
        return $this->input($id, $prop);
    }

    public function inputEmail($id, $prop) {
        $prop['classes'][] = 'easyui-textbox easyui-validatebox';
        $defaults = ['options'=>['multiline'=>true,'width'=>275,'height'=>60,'prompt'=>"'".jsLang('email')."'",'iconCls'=>"'icon-email'"]];
        $prop1 = array_replace_recursive($defaults, $prop);
        $prop1['attr']['type'] = 'text';
        return $this->input($id, $prop1);
    }

    public function inputFile($id, $prop) {
        unset($prop['break'], $prop['attr']['type']);
        $prop['classes'][] = 'easyui-filebox';
        if (empty($prop['options']['width'])) { $prop['options']['width'] = 350; }
        $this->mapEvents($prop);
        return $this->input($id, $prop);
    }

    /**
     * This function builds the combo box editor HTML for a datagrid to view GL Accounts
     * @return string set the editor structure
     */
    public function inputGL($id, $prop)
    {
        if (!empty($prop['types'])) {
            $js = "var {$id}Data = [];
var {$id}types=['".implode("','",$prop['types'])."'];
for (i=0; i<bizDefaults.glAccounts.rows.length; i++) {
    for (j=0; j<{$id}types.length; j++) {
        if (bizDefaults.glAccounts.rows[i].type == {$id}types[j] && bizDefaults.glAccounts.rows[i].inactive != '1') {
            {$id}Data.push(bizDefaults.glAccounts.rows[i]);
        }
    }
}";
        } elseif (!empty($prop['heading'])) { // just heading accounts
            $assets= [0, 2, 4, 6, 8, 12, 32, 34]; // gl_account types that are assets
            $accts = []; // load gl Accounts
            foreach (getModuleCache('phreebooks', 'chart', 'accounts') as $row) {
                if (empty($row['heading'])) { continue; }
                $row['asset'] = in_array($row['type'], $assets) ? 1 : 0;
                $row['type'] = viewFormat($row['type'], 'glType');
                $accts[] = $row; // need to remove keys
            }
            if (empty($accts)) { $accts = [['id'=>'','type'=>'','title'=>'No Primaries Defined']]; }
            $js = "var {$id}Data = ".json_encode($accts).";";
        } else {
            $js = "var {$id}Data=[];\njqBiz.each(bizDefaults.glAccounts.rows, function( key, value ) { if (value['inactive'] != '1') { {$id}Data.push(value); } });";
        }
        $this->jsHead[] = $js;
        $prop['classes'][]           = 'easyui-combogrid';
        $prop['options']['data']     = "{$id}Data";
        $prop['options']['width']    = "300,panelWidth:450,idField:'id',textField:'title',mode:'local'"; // ,selectOnNavigation:false,
        $prop['options']['rowStyler']= "function(index,row){ if (row.inactive=='1') { return { class:'row-inactive' }; } }";
        // Uncomment the line below to enable gl searching
        $prop['options']['inputEvents']= "jqBiz.extend({},jqBiz.fn.combogrid.defaults.inputEvents,{ keyup:function(e){ bizSelSearch('$id', jqBiz(this).val()); } })";
        if (!empty($prop['attr']['value'])) { $prop['options']['value'] = "'".$prop['attr']['value']."'"; }
        $this->mapEvents($prop);
        $prop['options']['columns']= "[[{field:'id',title:'".jsLang('gl_account')."',width:130},{field:'title',title:'".jsLang('title')."',width:210},{field:'type',title:'".jsLang('type')."',width:160}]]";
        unset($prop['attr']['type'],$prop['attr']['value']);
        return  $this->input($id, $prop);
    }

    public function inputInventory($id, $prop) {
        $defaults = ['width'=>250, 'panelWidth'=>350, 'delay'=>500, //'iconCls'=>"'icon-search'", 'hasDownArrow'=>'false',
            'idField'=>"'id'", 'textField'=>"'description_short'", 'mode'=>"'remote'"];
        $defaults['url']     = "'".BIZUNO_AJAX."&bizRt=inventory/main/managerRows&clr=1'";
        $defaults['callback']= "jqBiz('#item_cost').val(data.item_cost); jqBiz('#full_price').val(data.full_price);";
        $defaults['columns'] = "[[{field:'id',hidden:true},{field:'sku',title:'".jsLang('sku')."',width:100},{field:'description_short',title:'".jsLang('description')."',width:200}]]";
        // override defaults
        $prop['options'] = !empty($prop['defaults']) ? array_merge($defaults, $prop['defaults']) : $defaults;
        $prop['classes'][]              = 'easyui-combogrid';
        $prop['options']['onBeforeLoad']= "function () { var newValue=jqBiz('#$id').combogrid('getValue'); if (newValue.length < 2) return false; }";
        $prop['options']['onClickRow']  = "function (id, data) { {$prop['options']['callback']} }";
        unset($prop['options']['callback'], $prop['attr']['type']);
        if (!empty($prop['attr']['value']) && empty($attr['data'])) {
            $selText = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_short', "id='{$prop['attr']['value']}'");
            $this->jsReady[] = "jqBiz('#$id').combogrid({data:".json_encode([['id'=>$prop['attr']['value'], 'description_short'=>$selText]])."});";
        }
        return $this->input($id, $prop);
    }

    public function inputMonth($id, $prop) {
        return $this->input($id, $prop);
    }

    public function inputNumber($id, $prop, $precision=false) {
        $prop['classes'][] = 'easyui-numberbox';
        $prop['styles']['text-align'] = 'right';
        if ($precision !== false) { $prop['options']['precision'] = 0; }
        if ( empty($prop['options']['width']) && empty($prop['attr']['size'])) { $prop['options']['width'] = 100; }
        if (!empty($prop['attr']['maxlength'])) { $this->jsReady[] = "jqBiz('#$id').numberbox('textbox').attr('maxlength', jqBiz('#$id').attr('maxlength'));"; }
        unset($prop['attr']['type']);
        $this->mapEvents($prop);
        return $this->input($id, $prop);
    }

    public function inputPassword($id, $prop) {
        if (empty($prop['styles']['width']))  { $prop['styles']['width']  = '300px'; }
//      if (empty($prop['styles']['height'])) { $prop['styles']['height'] = '38px'; }
        $prop['classes'][] = 'easyui-passwordbox';
        $prop['options']['prompt'] = "'".jsLang('password')."'";
        return $this->input($id, $prop);
    }

    public function inputRadio($id, $prop) {
        if ( empty($prop['attr']['name']))    { $prop['attr']['name'] = $id;  }
        if (!empty($prop['attr']['checked'])) { $prop['options']['checked'] = true; }
        if (!empty($prop['attr']['selected'])){ $prop['options']['checked'] = true; unset($prop['attr']['selected']); }
        unset($prop['attr']['checked']);
        $prop['position']  = 'after';
        $prop['classes'][] = 'easyui-radiobutton';
        $this->mapEvents($prop);
        return '&nbsp;'.$this->input('', $prop); // remove the id for radio as there are more than 1
    }

    public function inputRange($id, $prop) {
        return $this->input($id, $prop);
    }

    public function inputRaw($id, $prop) {
        $output = $this->addLabelFirst($id, $prop) . $prop['html'] . $this->addLabelLast($id, $prop);
        if (!empty($prop['tip'])) { $output .= $this->addToolTip($id, $prop['tip']); }
        return $output . (!empty($prop['break']) ? '<br />' : '');
    }

    public function inputSearch($id, $prop) {
        return $this->input($id, $prop);
    }

    public function inputSelect($id, $prop) {
        if (!empty($prop['attr']['id'])) { $id = $prop['attr']['id']; }
        $idCln = str_replace(['[',']'], '', $id);
        $this->addID($id, $prop);
        $first = $this->addLabelFirst($id, $prop);
        $last  = $this->addLabelLast($id, $prop);
        if (!empty($prop['jsBody'])) { $this->jsBody[] = $prop['jsBody']; } // new way
        $prop['classes']['ui']   = "easyui-combobox";
        if (empty($prop['values'])) { $prop['values'] = [['id'=>'','text'=>lang('select')]]; }
        $this->jsHead[] = "var sel_{$idCln} = ".json_encode(array_values($prop['values'])).";";
        if (empty($prop['options']['data'])) { $prop['options']['data'] = "sel_{$idCln}"; }
        if (!isset($prop['attr']['value'])) { // set the default value as the first select element
            $selVals= $prop['values'];
            $def    = array_shift($selVals);
            $prop['attr']['value'] = isset($def['id']) ? $def['id'] : '';
        }
        if (!is_array($prop['attr']['value'])) {
            $prop['options']['value'] = "'".str_replace("'", "\\'", $prop['attr']['value'])."'"; // handle single quote in value variable
        } else {
            $prop['options']['value'] = htmlspecialchars(json_encode($prop['attr']['value']));
        }
        if ( empty($prop['options']['editable']))  { $prop['options']['editable']  = 'false'; }
        if ( empty($prop['options']['width']))     { $prop['options']['width']     = 200; }
        if ( empty($prop['options']['valueField'])){ $prop['options']['valueField']= "'id'"; }
        if ( empty($prop['options']['textField'])) { $prop['options']['textField'] = "'text'"; }
        unset($prop['attr']['value'], $prop['attr']['type']);
        $this->mapEvents($prop);
        $field = '<select'.$this->addAttrs($prop).'></select>';
        if (!empty($prop['tip'])) { $field .= $this->addToolTip($id, $prop['tip']); }
        $output = $first . $field . $last;
        return $output . (!empty($prop['break']) ? '<br />' : '');
    }

    public function inputSpinner($id, $prop) {
//      if (empty($prop['styles']['width']))  { $prop['styles']['width']  = '300px'; }
//      if (empty($prop['styles']['height'])) { $prop['styles']['height'] = '38px'; }
        if (!empty($prop['events']['onChange'])) {
            $prop['options']['onSpinUp']  = "function () { {$prop['events']['onChange']} }";
            $prop['options']['onSpinDown']= "function () { {$prop['events']['onChange']} }";
            unset($prop['events']['onChange']);
        }
        $prop['classes'][] = 'easyui-numberspinner';
        $prop['options']['spinAlign'] = "'horizontal'";
        return $this->input($id, $prop);
    }

    public function inputTax($id, $prop) {
        $defaults = ['type'=>'c', 'callback'=>false, 'target'=>''];
        if (!empty($prop['defaults']))    { $defaults = array_merge($defaults, $prop['defaults']); }
        if ( empty($defaults['callback'])){ $defaults['callback'] = "totalUpdate('inputTax');"; }
        $prop['classes'][]           = 'easyui-combogrid';
        $prop['options']['width']    = "180,panelWidth:230,delay:500,idField:'id',textField:'text'";
        $prop['options']['data']     = "[]";
//      if (!empty($prop['attr']['value'])) { $prop['options']['value'] = $prop['attr']['value']; }
//        $prop['options']['rowStyler']= "function(index,row){ if (row.status>0) { return { class:'row-inactive' }; } }";
        $prop['options']['onSelect'] = "function(id, data) { {$defaults['callback']} }";
        $prop['options']['columns']  = "[[{field:'id',hidden:true},{field:'status',hidden:true},{field:'text',title:'".jsLang('journal_main_tax_rate_id')."',width:160},{field:'tax_rate',title:'".jsLang('amount')."',align:'center',width:70}]]";
        unset($prop['attr']['type']);
        if (!empty($defaults['data'])) { $data = $defaults['data']; }
        else { $data = json_encode(viewSalesTaxDropdown($defaults['type'], $defaults['target'])); } //"bizDefaults.taxRates.{$defaults['type']}.rows";
        $this->jsReady[] = "jqBiz('#$id').combogrid({data:$data});";
        return $this->input($id, $prop);
    }

    public function inputTel($id, $prop) {
        // restrict to numbers, dots or dashes
        return $this->input($id, $prop);
    }

    public function inputText($id, $prop) {
        $this->addID($id, $prop);
        if (!empty($prop['inner']) && !empty($prop['label'])) { $prop['options']['prompt'] = $prop['label']; }
        if (in_array($prop['attr']['type'], ['hidden'])) { unset($prop['break']); return $this->input($id, $prop); }
        $prop['classes'][] = 'easyui-textbox';
        if (!empty($prop['required'])) { $prop['options']['required'] = true; }
        if ( empty($prop['options']['width'])) { // patch for Chrome ignoring size value
            $prop['options']['width'] = !empty($prop['attr']['size']) ? max(40, intval($prop['attr']['size']*7)) : 200;
            unset($prop['attr']['size']);
        }
        if (!empty($prop['attr']['maxlength'])) { $this->jsReady[] = "jqBiz('#{$prop['attr']['id']}').textbox('textbox').attr('maxlength', jqBiz('#{$prop['attr']['id']}').attr('maxlength'));"; }
        $this->mapEvents($prop);
        return $this->input($id, $prop);
    }

    public function inputTextarea($id, $prop) {
        $this->addID($id, $prop);
        if (empty($prop['attr']['rows'])) { $prop['attr']['rows'] = 20; }
        if (empty($prop['attr']['cols'])) { $prop['attr']['cols'] = 60; }
        $content = '';
        $field  = $this->addLabelFirst($id, $prop);
        $field .= '<textarea';
        foreach ($prop['attr'] as $key => $value) {
            if (in_array($key, ['type', 'maxlength'])) { continue; }
            if ($key == 'value') { $content = $value; continue; }
            $field .= ' ' . $key . '="' . str_replace('"', '&quot;', $value) . '"';
        }
        $field .= ">" . htmlspecialchars($content) . "</textarea>\n";
        $field .= $this->render('', ['icon'=>'edit','size'=>'small','label'=>lang('edit'),'events'=>['onClick'=>"tinymceInit('$id');"]]);
        $field .= $this->addLabelLast($id, $prop);
        if (!empty($prop['tip'])) { $field .= $this->addToolTip($id, $prop['tip']); }
        $this->jsBody['tinyMCE'] = "jqBiz.cachedScript('$this->htmlEditor');";
        if (!empty($prop['break'])) { $field .= '<br />'; }
        return $field;
    }

    public function inputTime($id, $prop) {
        // time spinner
        return $this->input($id, $prop);
    }

    public function inputUrl($id, $prop) {
        return $this->input($id, $prop);
    }

    public function selCurrency($id, $prop) {
        if (empty($prop['attr']['value'])) { $prop['attr']['value'] = getDefaultCurrency(); }
        if (sizeof(getModuleCache('phreebooks', 'currency', 'iso')) > 1) {
            $prop['attr']['type'] = 'select';
            $prop['values']       = viewDropdown(getModuleCache('phreebooks', 'currency', 'iso'), "code", "title");
            unset($prop['attr']['size']);
            $onChange = !empty($prop['callback']) ? " {$prop['callback']}(newVal, oldVal);" : '';
//          $onChange = "setCurrency(newVal);" . (!empty($prop['callback']) ? " {$prop['callback']}(newVal, oldVal);" : '');
            $this->jsReady[] = "jqBiz('#$id').combobox({editable:false, onChange:function(newVal, oldVal){ $onChange } });";
        } else {
            $prop['attr']['type'] = 'hidden';
        }
        return $this->render($id, $prop) ;
    }

    public function selNoYes($id, $prop) {
        if (!empty($prop['attr']['value'])) { $prop['attr']['checked'] = true; }
        $prop['attr']['type'] = 'checkbox';
        return $this->render($id, $prop);
    }

    /***************************** Media ******************/

    public function media() {

    }

    public function mediaVideo() {

    }

    public function mediaAudio() {

    }

    public function mediaGoogleMaps() {

    }

    public function mediaYouTube() {

    }

    /*     * *************************** APIs ***************** */

    /*     * *************************** Attributes ***************** */

    /**
     * Adds the attributes to a input field.
     * @param array $prop - field properties
     * @return HTML string
     */
    private function addAttrs($prop=[]) {
        $field = '';
        if (!empty($prop['options'])) {
            $tmp = [];
            foreach ($prop['options'] as $key => $value) { $tmp[] = "$key:$value"; }
            $prop['attr']['data-options'] = "{".implode(',', $tmp)."}";
        }
        if (!empty($prop['attr'])) { foreach ($prop['attr'] as $key => $value) {
            $field .= ' '.$key.'="'.str_replace('"', '\"', $value).'"'; // was str_replace('"', '&quot;', $value)
        } }
        if (!empty($prop['classes'])) { $field .= $this->addClasses($prop['classes']); }
        if (!empty($prop['styles']))  { $field .= $this->addStyles($prop['styles']); }
        if (!empty($prop['events']))  { $field .= $this->addEvents($prop['events']); }
        return $field;
    }

    /**
     * Takes all classes of a field and puts them into proper HTML format
     * @param array $arrClasses
     * @return HTMLstring
     */
    private function addClasses($arrClasses = []) {
        if (!is_array($arrClasses)) { $arrClasses = [$arrClasses]; }
        return ' class="' . implode(' ', $arrClasses) . '"';
    }

    /**
     * Takes all events of a field and puts them into proper HTML format
     * @param array $arrEvents - list of events
     * @return string - HTML
     */
    private function addEvents($arrEvents = []) {
        if (!is_array($arrEvents)) { $arrEvents = [$arrEvents]; }
        $output = '';
        foreach ($arrEvents as $key => $value) { $output .= ' ' . $key . '="' . str_replace('"', '\"', $value) . '"'; }
        return $output;
    }

    /**
     * Builds an array of properties for a datagrid used when passing through JavaScript
     * @param array $props
     * @return string - ready to
     */
    public function addGridProps($props=[]) {
        $options = [];
        if (!empty($props['attr']))  { foreach ($props['attr']   as $key => $value) { $options[] = "$key:".encodeType($value); } }
        if ( isset($props['events'])){ foreach ($props['events'] as $key => $value) { $options[] = "$key:$value"; } }
        $cols = [];
        foreach ($props['columns'] as $col => $settings) {
            $settings['attr']['field'] = $col;
            $settings['attr']['title'] = isset($settings['label']) ? $settings['label'] : $col;
            $temp = [];
            foreach ($settings['attr'] as $key => $value) { $temp[] = "$key:" . encodeType($value); }
            if (!empty($settings['events'])) { foreach ($settings['events'] as $key => $value) { $temp[] = "$key:$value"; } }
            $cols[] = "{ ".implode(",", $temp)." }";
        }
        $options[] = "columns:[[".implode(",", $cols)."]]";
        return "{ ".implode(",", $options)." }";
    }

    /**
     * Maps the events to the EasyUI format
     * @param array $prop - field properties
     * @return modifies $prop
     */
    private function mapEvents(&$prop) {
        if (empty($prop['events'])) { return; }
        foreach ($prop['events'] as $key => $event) {
            //msgDebug("\nmapEvents with key = $key and event = $event");
            $action = false;
            switch($key) {
                case 'onBlur':   $newKey = 'onChange'; $action = "function (newVal, oldVal) { ".$event." }"; break;
                case 'onChange': $newKey = 'onChange'; $action = "function (newVal, oldVal) { ".$event." }"; break;
                case 'onSelect': $newKey = 'onSelect'; $action = "function (index, row) { ".$event." }";     break;
                default: // do nothing
            }
            if ($action) {
                msgDebug("\nAdding to options array with key = $key and action = $action");
                $prop['options'][$newKey] = $action;
                unset($prop['events'][$key]);
            }
        }
    }

    /**
     * Cleans up the element id and name attributes to work with array inputs
     * @param string $id - DOM id
     * @param array $prop - DOM properties
     */
    private function addID($id='', &$prop=[]) {
        if ($id && !isset($prop['attr']['name'])) { $prop['attr']['name'] = $id; }
        if     (isset($prop['attr']['id'])) { } // use it
        elseif (strpos($id, '[]'))          { unset($prop['attr']['id']); } // don't show id attribute if generic array
        elseif ($id) {
            $prop['attr']['id'] = str_replace('[', '_', $id); // clean up for array inputs causing html errors
            $prop['attr']['id'] = str_replace(']', '', $prop['attr']['id']);
        }
        if (isset($prop['attr']['required']) && $prop['attr']['required']) { $prop['classes'][] = 'easyui-validatebox'; unset($prop['attr']['required']); }
    }

    /**
     * Adds the label tag before the field if requested
     * @param string $id - DOM field id
     * @param array $prop - properties
     * @return HTML string
     */
    private function addLabelFirst($id, $prop) {
        $field = '';
        if ( empty($prop['label'])) { return $field; }
        if (!empty($prop['attr']['type']) && $prop['attr']['type'] == 'hidden') { return $field; }
        if ( empty($prop['position'])) {
            $el = ['styles'=>['vertical-align'=>'top'],'attr'=>['type'=>'label','for'=>$id]];
            if (!empty($prop['lblStyle'])) { $el['styles'] = array_merge($el['styles'], $prop['lblStyle']); }
            $field .= $this->htmlElOpen('', $el) . $prop['label'].'</label>&nbsp;';
//            if (!empty($prop['tip'])) { $field .= $this->addToolTip($id, $prop['tip']); }
        }
        return $field;
    }

    /**
     * Adds the label tag after the field if requested
     * @param string $id - DOM field id
     * @param array $prop - properties
     * @return HTML string
     */
    private function addLabelLast($id, $prop) {
        $field = '';
        if ( empty($prop['label'])) { return $field; }
        if (!empty($prop['attr']['type']) && $prop['attr']['type'] == 'hidden') { return $field; }
        if (!empty($prop['position'])     && $prop['position'] == 'after') {
            $mins  = !empty($prop['attr']['type']) && in_array($prop['attr']['type'], ['checkbox','radio']) ? 'min-width:60px;min-height:32px;' : '';
            $styles= "vertical-align:top;$mins";
//          if (!empty($prop['tip'])) { $field .= $this->addToolTip($id, $prop['tip']); }
            $field .= '<label for="'.$id.'" class="fldLabel" style="'.$styles.'">&nbsp;'.$prop['label'].'</label>';
        }
        return $field;
    }

    /**
     * Adds the messageStack to the trace file.
     * @global type $msgStack
     * @return string
     */
    public function addMsgStack() {
        global $msgStack;
        $msgStack->error = array_merge_recursive($msgStack->error, getUserCache('msgStack'));
        clearUserCache('msgStack');
        if (sizeof($msgStack->error)) {
            if (!$msg = json_encode($msgStack->error)) { // msgStack is malformed
                $msg = '[]';
                msgDebug("\nEncoding the messages in json returned with error: " . json_last_error_msg());
            }
            return "var messages = $msg;\ndisplayMessage(messages);";
        }
        return '';
    }

    /**
     * Combines the requested styles and into an HTML string
     * @param type $arrStyles
     * @return type
     */
    private function addStyles($arrStyles = []) {
        if (!is_array($arrStyles)) {
            $arrStyles = [$arrStyles];
        }
        $styles = [];
        foreach ($arrStyles as $key => $value) {
            $styles[] = $key . ':' . $value;
        }
        return ' style="' . implode(';', $styles) . ';"';
    }

    /**
     * Adds the tooltip to the field if requested
     * @param string $id - DOM field id
     * @param string $tip - tip to display
     * @return string
     */
    private function addToolTip($id, $tip='') {
        $opts = ['showEvent'=>"'click'",'position'=>"'bottom'",'onShow'=>"function(){ jqBiz(this).tooltip('tip').css({width:'450px'}); }"];
        $prop = ['classes'=>["icon-help", "easyui-tooltip"],'styles'=>['border'=>0,'display'=>'inline-block;vertical-align:middle','height'=>'16px','min-width'=>'16px','cursor'=>'pointer'],
            'options'=>$opts,'attr'=>['type'=>'span','id'=>"tip_$id",'title'=>$tip]];
//        $this->jsReady[] = "jqBiz('#tip_$id').tooltip({ $opts,content:'".addslashes($tip)."'});";
        return $this->render('', $prop);
    }
}

/** *************************** Grid Editors ***************** */
function dgEditComboTax($name) {
    return "{type:'combogrid', options:{width:130, panelWidth:750, delay:900, idField:'id', textField:'short_name', mode:'remote',
    url:'".BIZUNO_AJAX."&bizRt=contacts/main/managerRows&clr=1&type=v', selectOnNavigation:false,
    onSelect:function(index,row) {
bizSelEdSet('$name',curIndex,'cID',row.id);
bizTextEdSet('$name',curIndex,'cTitle',row.short_name);
bizTextEdSet('$name',curIndex,'text',row.primary_name);
bizGridEdSet('$name',curIndex,'glAcct',row.gl_account);
bizNumEdSet('$name',curIndex,'rate', 0); },
    columns: [[
      {field:'id',          hidden:true},
      {field:'short_name',  width:100,title:'".jsLang('contacts_short_name')."'},
      {field:'primary_name',width:200,title:'".jsLang('address_book_primary_name')."'},
      {field:'city',        width:100,title:'".jsLang('address_book_city')."'},
      {field:'state',       width: 50,title:'".jsLang('address_book_state')."'} ]] }}";
}

/**
 * This function builds the combo box editor HTML for a grid to view GL Accounts
 * @param string $onClick - JavaScript to run on click event
 * @return string set for the editor structure
 */
function dgEditContact($onClick='', $type='c') {
    return "{type:'combogrid',options:{ data:pbChart, mode:'local', width:300, panelWidth:450, idField:'id', textField:'title', onClickRow:function(index, row){ $onClick },
inputEvents:jqBiz.extend({},jqBiz.fn.combogrid.defaults.inputEvents,{ keyup:function(e){ glComboSearch(jqBiz(this).val()); } }),
rowStyler:  function(index,row){ if (row.inactive=='1') { return { class:'row-inactive' }; } },
columns:    [[{field:'id',title:'".jsLang('gl_account')."',width:130},{field:'title',title:'".jsLang('title')."',width:210},{field:'type',title:'".jsLang('type')."',width:160}]]}}";
}

/*
function htmlComboContact($id, $props=[])
{
    $defaults = ['type'=>'c','store'=>false,'callback'=>'contactsDetail','opt1'=>'b','opt2'=>'']; // opt1=>suffux, opt2=>fill
    $attr = array_replace($defaults, $props);
    return html5($id, ['label'=>lang('search'),'classes'=>['easyui-combogrid'],'attr'=>['data-options'=>"
        width:130, panelWidth:750, delay:900, idField:'id', textField:'primary_name', mode: 'remote',
        url:'".BIZUNO_AJAX."&bizRt=contacts/main/managerRows&clr=1&type={$attr['type']}&store=".($attr['store']?'1':'0')."',
        onBeforeLoad:function (param) { var newValue=jqBiz('#$id').combogrid('getValue'); if (newValue.length < 3) { return false; } },
        selectOnNavigation:false,
        onClickRow:  function (idx, row){ {$attr['callback']}(row, '{$attr['opt1']}', '{$attr['opt2']}'); },
        columns: [[{field:'id', hidden:true},{field:'email', hidden:true},
            {field:'short_name',  title:'".jsLang('contacts_short_name')."', width:100},
            {field:'type',        hidden:".(strlen($attr['type'])>1?'false':'true').",title:'".jsLang('contacts_type')."', width:100},
            {field:'primary_name',title:'".jsLang('address_book_primary_name')."', width:200},
            {field:'address1',    title:'".jsLang('address_book_address1')."', width:100},
            {field:'city',        title:'".jsLang('address_book_city')."', width:100},
            {field:'state',       title:'".jsLang('address_book_state')."', width: 50},
            {field:'postal_code', title:'".jsLang('address_book_postal_code')."', width:100},
            {field:'telephone1',  title:'".jsLang('address_book_telephone1')."', width:100}]]"]]);
}

"{type:'combogrid',options:{width:130,panelWidth:750,delay:900,idField:'id',textField:'primary_name',mode:'remote',
    url:bizunoAjax+'&bizRt=contacts/main/managerRows&clr=1&type=v&rows=9999',selectOnNavigation:false,
    columns: [[
      {field:'id',          hidden:true},
      {field:'short_name',  width:100,title:'".jsLang('contacts_short_name')."'},
      {field:'primary_name',width:200,title:'".jsLang('address_book_primary_name')."'},
      {field:'address1',    width:100,title:'".jsLang('address_book_address1')."'},
      {field:'city',        width:100,title:'".jsLang('address_book_city')."'},
      {field:'state',       width: 50,title:'".jsLang('address_book_state')."'},
      {field:'postal_code', width:100,title:'".jsLang('address_book_postal_code')."'},
      {field:'telephone1',  width:100,title:'".jsLang('address_book_telephone1')."'}
    ]] } }"
 */

/**
 * Creates the grid editor for a currency number box
 * @param string $onChange - JavaScript to run on change event
 * @param boolean $precision - set to true for number precision; false for currency precision
 * @return string - grid editor JSON
 */
function dgEditCurrency($onChange='', $precision=false) {
    $iso  = getDefaultCurrency();
    $props= getModuleCache('phreebooks', 'currency', 'iso', $iso, 'USD');
    $prec = $precision ? getModuleCache('bizuno','settings','locale','number_precision',2) : $props['dec_len'];
    $dec  = str_replace("'", "\\'", $props['dec_pt']);
    $tsnd = str_replace("'", "\\'", $props['sep']);
    $pfx  = !empty($props['prefix']) ? str_replace("'", "\\'", $props['prefix'])." " : '';
    $sfx  = !empty($props['suffix']) ? " ".str_replace("'", "\\'", $props['suffix']) : '';
    return "{type:'numberbox',options:{precision:$prec,decimalSeparator:'$dec',groupSeparator:'$tsnd',prefix:'$pfx',suffix:'$sfx',onChange:function(newValue, oldValue){ $onChange } } }";
}

/**
 * This function builds the combo box editor HTML for a grid to view GL Accounts
 * @param string $onClick - JavaScript to run on click event
 * @return string set for the editor structure
 */
function dgEditGL($onClick='') {
    return "{type:'combogrid',options:{ data:pbChart, mode:'local', width:300, panelWidth:450, idField:'id', textField:'title', onClickRow:function(index, row){ $onClick },
inputEvents:jqBiz.extend({},jqBiz.fn.combogrid.defaults.inputEvents,{ keyup:function(e){ glComboSearch(jqBiz(this).val()); } }),
rowStyler:  function(index,row){ if (row.inactive=='1') { return { class:'row-inactive' }; } },
columns:    [[{field:'id',title:'".jsLang('gl_account')."',width:130},{field:'title',title:'".jsLang('title')."',width:210},{field:'type',title:'".jsLang('type')."',width:160}]]}}";
}

/**
 * Creates the grid editor for a number box
 * @param string $onChange - JavaScript to run on change event
 * @return string set for the editor structure
 */
function dgEditNumber($onChange='') {
    $prec  = getModuleCache('bizuno','settings','locale','number_precision',2);
    $tsnd  = str_replace("'", "\\'", getModuleCache('bizuno','settings','locale','number_thousand',','));
    $dec   = str_replace("'", "\\'", getModuleCache('bizuno','settings','locale','number_decimal', '.'));
    $prefix= getModuleCache('bizuno','settings','locale','number_prefix', '');
    $pfx   = !empty($prefix) ? str_replace("'", "\\'", $prefix)." " : '';
    $suffix= getModuleCache('bizuno','settings','locale','number_suffix', '');
    $sfx   = !empty($suffix) ? " ".str_replace("'", "\\'", $suffix) : '';
    return "{type:'numberbox',options:{precision:$prec,decimalSeparator:'$dec',groupSeparator:'$tsnd',prefix:'$pfx',suffix:'$sfx',onChange:function(newValue, oldValue){ $onChange } } }";
}

/**
 * Creates the datagrid editor for a tax combogrid
 * @param string $id - datagrid ID
 * @param string $field - datagrid field ID to set
 * @param char $type - c for customers or v for vendors
 * @param string $xClicks - callback JavaScript, if any
 * @return string set for the editor structure
 */
function dgEditTax($id, $field, $type='c', $xClicks='') {
    return "{type:'combogrid',options:{data: bizDefaults.taxRates.$type.rows,width:120,panelWidth:210,idField:'id',textField:'text',
        onClickRow:function (index, row) { jqBiz('#$id').edatagrid('getRows')[curIndex]['$field'] = row.id; $xClicks },
        rowStyler:function(idx, row) { if (row.status==1) { return {class:'journal-waiting'}; } else if (row.status==2) { return {class:'row-inactive'}; }  },
        columns: [[{field:'id',hidden:true},{field:'text',width:120,title:'".jsLang('journal_main_tax_rate_id')."'},{field:'tax_rate',width:70,title:'".jsLang('amount')."',align:'center'}]]
    }}";
}

function dgEditText() {
    return 'text';
}

function dgGetValue($id, $type) {
    switch ($type) {
        case 'select':  return "bizSelGet('$id')"; // covers select,
        case 'checkbox':return "bizCheckBoxGet('$id')"; // covers radio, checkbox,
        case 'integer':
        case 'float':   return "bizNumGet('$id')"; // covers numbers
        default:        return "bizTextGet('$id')"; // covers text, hidden,
    }
}

function dgSorterDate() {
    $fmtDate = getModuleCache('bizuno', 'settings', 'locale', 'date_short', 'm/d/Y');
    switch ($fmtDate) {
        case 'Y/m/d': $delim='/'; $type=0; break;
        case 'Y-m-d': $delim='-'; $type=0; break;
        case 'Y.m.d': $delim='.'; $type=0; break;
        case 'd/m/Y': $delim='/'; $type=1; break;
        case 'd.m.Y': $delim='.'; $type=1; break;
        case 'm/d/Y': $delim='/'; $type=2; break;
        default:      return "function(a,b) { return (a>b?1:-1); }"; // covers Ymd but fails for dmY (no solution at this time, perhaps substr and rebuild as Ymd)
    }
    if ($type==0) {
        return "function(a,b) { a=a.split('$delim'); b=b.split('$delim'); if (a[0]==b[0]) { if (a[1]==b[1]) { return (a[2]>b[2]?1:-1); } else { return (a[1]>b[1]?1:-1); } } else { return (a[0]>b[0]?1:-1); } }";
    } elseif ($type==1) {
        return "function(a,b) { a=a.split('$delim'); b=b.split('$delim'); if (a[2]==b[2]) { if (a[1]==b[1]) { return (a[0]>b[0]?1:-1); } else { return (a[1]>b[1]?1:-1); } } else { return (a[2]>b[2]?1:-1); } }";
    } else { // $type==2, USA
        return "function(a,b) { a=a.split('$delim'); b=b.split('$delim'); if (a[2]==b[2]) { if (a[0]==b[0]) { return (a[1]>b[1]?1:-1); } else { return (a[0]>b[0]?1:-1); } } else { return (a[2]>b[2]?1:-1); } }";
    }
}