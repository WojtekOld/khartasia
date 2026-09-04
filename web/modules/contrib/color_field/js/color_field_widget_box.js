/**
 * @file
 * Color Field jQuery.
 */

(function ($) {
  jQuery.fn.addColorPicker = function (props) {
    if (!props) {
      props = [];
    }

    props = jQuery.extend(
      {
        currentColor: '',
        blotchElemType: 'button',
        blotchClass: 'colorBox',
        blotchTransparentClass: 'transparentBox',
        addTransparentBlotch: true,
        clickCallback(ignoredColor) {},
        iterationCallback: null,
        fillString: '&nbsp;',
        fillStringX: '?',
        colors: [
          '#AC725E',
          '#D06B64',
          '#F83A22',
          '#FA573C',
          '#FF7537',
          '#FFAD46',
          '#42D692',
          '#16A765',
          '#7BD148',
          '#B3DC6C',
          '#FBE983',
          '#92E1C0',
          '#9FE1E7',
          '#9FC6E7',
          '#4986E7',
          '#9A9CFF',
          '#B99AFF',
          '#C2C2C2',
          '#CABDBF',
          '#CCA6AC',
          '#F691B2',
          '#CD74E6',
          '#A47AE2',
        ],
      },
      props,
    );

    this.addBlotchElement = function (color, blotchClass, index) {
      const elem = jQuery(`<${props.blotchElemType}/>`)
        .addClass(blotchClass)
        .attr('value', color)
        .attr('color', color)
        .attr('title', color);
      elem[0].style.backgroundColor = color;
      if (props.currentColor.toLowerCase() === color.toLowerCase()) {
        elem.addClass('active');
      }
      if (props.clickCallback) {
        elem.click(function (event) {
          event.preventDefault();
          jQuery(this).parent().children().removeClass('active');
          jQuery(this).addClass('active');
          props.clickCallback(jQuery(this).attr('color'));
        });
      }
      this.append(elem);
      if (props.iterationCallback) {
        props.iterationCallback(this, elem, color, index);
      }
    };

    for (let i = 0; i < props.colors.length; ++i) {
      const color = props.colors[i];
      this.addBlotchElement(color, props.blotchClass, i);
    }

    if (props.addTransparentBlotch) {
      this.addBlotchElement('', props.blotchTransparentClass);
    }

    return this;
  };
})(jQuery);
