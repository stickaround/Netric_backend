<?php
// $Header: /var/cvs/ant.aereus.com/lib/html_to_pdf/font.resolver.class.php,v 1.2 2006/01/30 00:39:21 administrator Exp $

require_once('font.constants.inc.php');

class FontResolver {
  var $families;
  var $aliases;
  var $overrides;
  var $ttf_mappings;

  var $ps_fonts;
  var $ps_fonts_counter;

  function setup_ttf_mappings($pdf) {
    foreach ($this->ttf_mappings as $typeface => $file) {
      pdf_set_parameter($pdf, "FontOutline", $typeface."=".PDFLIB_TTF_FONTS_REPOSITORY.$file); 
    };
  }

  function add_ttf_mapping($typeface, $file) {
    $this->ttf_mappings[$typeface] = $file;
  }

  function font_resolved($family, $weight, $style, $encoding) {
    return 
      isset($this->ps_fonts[$family]) and
      isset($this->ps_fonts[$family][$weight]) and
      isset($this->ps_fonts[$family][$weight][$style]) and
      isset($this->ps_fonts[$family][$weight][$style][$encoding]);
  }

  function resolve_font($family, $weight, $style, $encoding) {
    if (!$this->font_resolved($family, $weight, $style, $encoding)) {
      $this->ps_fonts[$family][$weight][$style][$encoding] = 'font'.$this->ps_fonts_counter;
      $this->ps_fonts_counter++;
    };
    return $this->ps_fonts[$family][$weight][$style][$encoding];
  }
  
  function FontResolver() {
    $this->families  = array();
    $this->aliases   = array();
    $this->overrides = array();
    $this->ttf_mappings = array();

    $this->ps_fonts = array();
    $this->ps_fonts_counter = 1;
  }

  function add_family_normal_encoding_override($family, $encoding, $normal, $italic, $oblique) {
    $this->overrides[$encoding][$family][WEIGHT_NORMAL][FS_NORMAL]  = $normal;
    $this->overrides[$encoding][$family][WEIGHT_NORMAL][FS_ITALIC]  = $italic;
    $this->overrides[$encoding][$family][WEIGHT_NORMAL][FS_OBLIQUE] = $oblique;
  }

  function add_family_bold_encoding_override($family, $encoding, $normal, $italic, $oblique) {
    $this->overrides[$encoding][$family][WEIGHT_BOLD][FS_NORMAL]  = $normal;
    $this->overrides[$encoding][$family][WEIGHT_BOLD][FS_ITALIC]  = $italic;
    $this->overrides[$encoding][$family][WEIGHT_BOLD][FS_OBLIQUE] = $oblique;
  }

  function add_normal_encoding_override($encoding, $normal, $italic, $oblique) {
    $this->add_family_normal_encoding_override(" ",$encoding, $normal, $italic, $oblique);
  }

  function add_bold_encoding_override($encoding, $normal, $italic, $oblique) {
    $this->add_family_bold_encoding_override(" ",$encoding, $normal, $italic, $oblique);
  }

  function get_global_encoding_override($weight, $style, $encoding) {
    return $this->get_family_encoding_override(" ", $weight, $style, $encoding);
  }

  function get_family_encoding_override($family, $weight, $style, $encoding) {
    if (!isset($this->overrides[$encoding])) { return ""; }
    if (!isset($this->overrides[$encoding][$family])) { return ""; }
    if (!isset($this->overrides[$encoding][$family][$weight])) { return ""; }
    if (!isset($this->overrides[$encoding][$family][$weight][$style])) { return ""; }
    return $this->overrides[$encoding][$family][$weight][$style];
  }

  function have_global_encoding_override($weight, $style, $encoding) {
    return $this->get_global_encoding_override($weight, $style, $encoding) !== "";
  }

  function have_family_encoding_override($family, $weight, $style, $encoding) {
    return $this->get_family_encoding_override($family, $weight, $style, $encoding) !== "";
  }

  function add_alias($alias, $family) { $this->aliases[$alias] = $family; }

  function add_normal_family($family, $normal, $italic, $oblique) {
    $this->families[$family][WEIGHT_NORMAL][FS_NORMAL]  = $normal;
    $this->families[$family][WEIGHT_NORMAL][FS_ITALIC]  = $italic;
    $this->families[$family][WEIGHT_NORMAL][FS_OBLIQUE] = $oblique;
  }

  function add_bold_family($family, $normal, $italic, $oblique) {
    $this->families[$family][WEIGHT_BOLD][FS_NORMAL]  = $normal;
    $this->families[$family][WEIGHT_BOLD][FS_ITALIC]  = $italic;
    $this->families[$family][WEIGHT_BOLD][FS_OBLIQUE] = $oblique;
  }

  function ps_font_family($family, $weight, $style, $encoding) {
    if ($this->have_alias($family)) {
      return $this->ps_font_family($this->aliases[$family], $weight, $style, $encoding);
    }

    // Check for family-specific encoding override
    if ($this->have_family_encoding_override($family, $weight, $style, $encoding)) {
      return $this->get_family_encoding_override($family, $weight, $style, $encoding);
    }

    // Check for global encoding override
    if ($this->have_global_encoding_override($weight, $style, $encoding)) {
      return $this->get_global_encoding_override($weight, $style, $encoding);
    }

    if (!isset($this->families[$family])) { return "/Times-Roman"; };
    if (!isset($this->families[$family][$weight])) { return "/Times-Roman"; };
    if (!isset($this->families[$family][$weight][$style])) { return "/Times-Roman"; };

    return $this->families[$family][$weight][$style];
  }

  function have_alias($family) { return isset($this->aliases[$family]); }
  function have_font_family($family) { return isset($this->families[$family]) or $this->have_alias($family); }
}

$g_font_resolver = new FontResolver();
$g_font_resolver_pdf = new FontResolver();

?>