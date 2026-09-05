/**
 * Professional drag-and-drop helpers for admin upload surfaces.
 * Fixes nested dragleave flicker and standardizes drop feedback.
 */

/**
 * @param {HTMLElement | null} dropzone
 * @param {{
 *   onFiles?: (files: File[]) => void,
 *   onFile?: (file: File) => void,
 *   multiple?: boolean,
 * }} options
 * @returns {() => void}
 */
export function bindDropzone(dropzone, options = {}) {
    if (!dropzone) return () => {};

    const { onFiles, onFile, multiple = false } = options;

    const clear = () => {
        dropzone.classList.remove('is-dragover');
    };

    const onEnter = (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add('is-dragover');
    };

    const onOver = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = 'copy';
        }
    };

    const onLeave = (e) => {
        e.preventDefault();
        e.stopPropagation();
        // Only clear when the pointer truly leaves the dropzone (not when
        // entering a child). Avoid depth counters — they desync with relatedTarget.
        const related = /** @type {Node | null} */ (e.relatedTarget);
        if (related && dropzone.contains(related)) return;
        clear();
    };

    const onDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        clear();
        const list = Array.from(e.dataTransfer?.files || []);
        if (!list.length) return;
        if (typeof onFiles === 'function') {
            onFiles(multiple ? list : list.slice(0, 1));
            return;
        }
        if (typeof onFile === 'function') {
            onFile(list[0]);
        }
    };

    dropzone.addEventListener('dragenter', onEnter);
    dropzone.addEventListener('dragover', onOver);
    dropzone.addEventListener('dragleave', onLeave);
    dropzone.addEventListener('drop', onDrop);

    return () => {
        dropzone.removeEventListener('dragenter', onEnter);
        dropzone.removeEventListener('dragover', onOver);
        dropzone.removeEventListener('dragleave', onLeave);
        dropzone.removeEventListener('drop', onDrop);
        clear();
    };
}

/**
 * Sync an ordered File[] into an <input type="file" multiple>.
 * @param {HTMLInputElement | null} input
 * @param {File[]} files
 */
export function assignFiles(input, files) {
    if (!input) return;
    const dt = new DataTransfer();
    files.forEach((file) => dt.items.add(file));
    input.files = dt.files;
}
