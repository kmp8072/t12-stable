Description of dompdf import
==============================

1. Download latest version from https://github.com/dompdf/dompdf

2. Reapply Totara hacks in following areas:
    - \Dompdf\Helpers::getFileContent() - Totara download restrictions
    - totara/appraisal/dompdf/lib/res/html.css - img max-width and height tweaks
    - totara/appraisal/dompdf/lib/res/html.css - remove field set CSS
    - totara/appraisal/dompdf/src/Css/Stylesheet.php - set ```$this->_page_styles = ["base" => new Style($this)]``` in constructor
        - Pull request to upstream DomPDF: <https://github.com/dompdf/dompdf/pull/1705>
        - Totara ticket: TL-16853
    - Convert continue statements within switch statements for PHP 7.3 compatibility.
        - totara/appraisal/dompdf/src/Dompdf.php
        - totara/appraisal/dompdf/lib/Cpdf.php
        - totara/appraisal/dompdf/src/Renderer/Text.php
        - totara/appraisal/dompdf/src/Css/Stylesheet.php

3. Bump up version in totara/appraisal/thirdpartylibs.xml

Petr Skoda
