(function (Drupal, once) {
  Drupal.behaviors.xnttSortableSelect = {
    attach(context, settings) {
      once('xntt-sortable-select', '.xntt-sortable-select', context).forEach(
        function (select) {
          const moveUpBtn = document.createElement('button');
          moveUpBtn.type = 'button';
          moveUpBtn.textContent = '↑';
          moveUpBtn.addEventListener('click', function () {
            for (let i = 0; i < select.options.length; ++i) {
              if (select.options[i].selected && i > 0) {
                const prevOption = select.options[i - 1];
                if (prevOption.selected) {
                  continue;
                }
                const option = select.options[i];
                select.insertBefore(option, prevOption);
                i--;
              }
            }
          });

          const moveDownBtn = document.createElement('button');
          moveDownBtn.type = 'button';
          moveDownBtn.textContent = '↓';
          moveDownBtn.addEventListener('click', function () {
            for (let i = select.options.length - 1; i >= 0; --i) {
              if (select.options[i].selected && i < select.options.length - 1) {
                const nextOption = select.options[i + 1];
                if (nextOption.selected) {
                  continue;
                }
                const option = select.options[i];
                select.insertBefore(option, nextOption.nextSibling);
              }
            }
          });

          const wrapper = document.createElement('div');
          wrapper.className = 'xntt-sortable-select-wrapper';
          select.parentNode.insertBefore(wrapper, select);
          wrapper.appendChild(select);
          wrapper.appendChild(moveUpBtn);
          wrapper.appendChild(moveDownBtn);
        },
      );
    },
  };
})(Drupal, once);
