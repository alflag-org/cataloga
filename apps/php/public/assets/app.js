(() => {
  const setupResourceTagEditor = () => {
    const root = document.querySelector('[data-tag-editor-root]');
    const editor = root?.querySelector('[data-tag-editor]');
    const addButton = root?.querySelector('[data-add-tag]');
    if (!editor || !addButton) return;

    const createRow = () => {
      const row = document.createElement('div');
      row.className = 'tag-row';
      row.dataset.tagRow = '';
      row.innerHTML = `
        <div class="field">
          <label>キー</label>
          <input type="text" name="tag_key[]">
        </div>
        <div class="field">
          <label>値</label>
          <input type="text" name="tag_value[]">
        </div>
        <button type="button" class="secondary-button" data-remove-tag>削除</button>
      `;
      return row;
    };

    addButton.addEventListener('click', () => {
      const row = createRow();
      editor.appendChild(row);
      row.querySelector('input')?.focus();
    });

    editor.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement) || !target.matches('[data-remove-tag]')) return;
      target.closest('[data-tag-row]')?.remove();
    });
  };

  const setupSettingsTagEditor = () => {
    const editor = document.querySelector('[data-settings-tag-editor]');
    const addButton = document.querySelector('[data-add-settings-tag]');
    if (!editor || !addButton) return;

    const renumber = () => {
      editor.querySelectorAll('[data-settings-tag-row]').forEach((row, index) => {
        row.querySelectorAll('input[type="checkbox"]').forEach((input) => {
          input.value = String(index);
        });
      });
    };

    const createRow = () => {
      const section = document.createElement('section');
      section.className = 'panel soft';
      section.dataset.settingsTagRow = '';
      section.innerHTML = `
        <div class="split">
          <div class="field">
            <label>キー</label>
            <input type="text" name="tag_key[]" required>
          </div>
          <div class="field">
            <label>ラベル</label>
            <input type="text" name="tag_label[]">
          </div>
        </div>
        <div class="field">
          <label>許可値</label>
          <input type="text" name="tag_values[]" placeholder="prod, staging, dev">
          <p class="meta">カンマ区切り。空の場合は自由入力として扱えます。</p>
        </div>
        <div class="actions">
          <label class="field inline"><input class="checkbox" type="checkbox" name="tag_required[]">必須</label>
          <label class="field inline"><input class="checkbox" type="checkbox" name="tag_free_value[]" checked>自由入力</label>
          <label class="field inline"><input class="checkbox" type="checkbox" name="tag_allow_empty[]">空値を許可</label>
          <button type="button" class="secondary-button" data-remove-settings-tag>削除</button>
        </div>
      `;
      return section;
    };

    addButton.addEventListener('click', () => {
      const row = createRow();
      editor.appendChild(row);
      renumber();
      row.querySelector('input')?.focus();
    });

    editor.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement) || !target.matches('[data-remove-settings-tag]')) return;
      target.closest('[data-settings-tag-row]')?.remove();
      renumber();
    });
  };

  setupResourceTagEditor();
  setupSettingsTagEditor();
})();
