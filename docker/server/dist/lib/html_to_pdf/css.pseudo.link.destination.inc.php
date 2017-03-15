<?php

class CSSPseudoLinkDestination extends CSSProperty {
  function CSSPseudoLinkDestination() { $this->CSSProperty(true, true); }

  function default_value() { return ""; }

  function parse($value) { 
    return $value;
  }

  function value2ps($value) {
    return "/".$value;
  }
}

register_css_property('-html2ps-link-destination', new CSSPseudoLinkDestination);

?>