document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('active-groups-ru');
    const groupItems = document.querySelectorAll('.group-item');
    let draggedItem = null;

    groupItems.forEach(item => {
        const itemControl = item.querySelector('.item-control');
        const grabIcon = itemControl.querySelector('.grab-icon');
        const inputElement = item.querySelector('.item-input input');

        // Активация/деактивация перетаскивания
        const setDraggable = (isDraggable) => {
            item.setAttribute('draggable', isDraggable.toString());
        };

        // Mousedown handler только на иконке
        const handleGrabIconMouseDown = (event) => {
            setDraggable(true);
        };

        // Mouseup handler
        const handleItemControlMouseUp = (event) => {
            setDraggable(false);
        };

        // Добавляем обработчики на иконку grab-icon
        grabIcon.addEventListener('mousedown', handleGrabIconMouseDown);
        itemControl.addEventListener('mouseup', handleItemControlMouseUp);


        // Drag and Drop Events
        item.addEventListener('dragstart', (event) => {
            // Проверяем, не был ли клик внутри input
            if (event.target === inputElement) {
                event.preventDefault(); // Отменяем перетаскивание
                return;
            }

            draggedItem = item;
            item.classList.add('dragging');
        });

        item.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!draggedItem || item === draggedItem) return;
            item.classList.add('drag-over');
        });

        item.addEventListener('dragleave', () => {
            item.classList.remove('drag-over');
        });

        item.addEventListener('drop', (event) => {
            event.preventDefault();
            if (!draggedItem || item === draggedItem) return;
            item.classList.remove('drag-over');
            form.insertBefore(draggedItem, item);
        });

        item.addEventListener('dragend', () => {
            groupItems.forEach(item => item.classList.remove('drag-over', 'dragging'));
            draggedItem = null;
            setDraggable(false);
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('active-groups-by');
    const groupItems = document.querySelectorAll('.group-item');
    let draggedItem = null;

    groupItems.forEach(item => {
        const itemControl = item.querySelector('.item-control');
        const grabIcon = itemControl.querySelector('.grab-icon');
        const inputElement = item.querySelector('.item-input input');

        // Активация/деактивация перетаскивания
        const setDraggable = (isDraggable) => {
            item.setAttribute('draggable', isDraggable.toString());
        };

        // Mousedown handler только на иконке
        const handleGrabIconMouseDown = (event) => {
            setDraggable(true);
        };

        // Mouseup handler
        const handleItemControlMouseUp = (event) => {
            setDraggable(false);
        };

        // Добавляем обработчики на иконку grab-icon
        grabIcon.addEventListener('mousedown', handleGrabIconMouseDown);
        itemControl.addEventListener('mouseup', handleItemControlMouseUp);


        // Drag and Drop Events
        item.addEventListener('dragstart', (event) => {
            // Проверяем, не был ли клик внутри input
            if (event.target === inputElement) {
                event.preventDefault(); // Отменяем перетаскивание
                return;
            }

            draggedItem = item;
            item.classList.add('dragging');
        });

        item.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!draggedItem || item === draggedItem) return;
            item.classList.add('drag-over');
        });

        item.addEventListener('dragleave', () => {
            item.classList.remove('drag-over');
        });

        item.addEventListener('drop', (event) => {
            event.preventDefault();
            if (!draggedItem || item === draggedItem) return;
            item.classList.remove('drag-over');
            form.insertBefore(draggedItem, item);
        });

        item.addEventListener('dragend', () => {
            groupItems.forEach(item => item.classList.remove('drag-over', 'dragging'));
            draggedItem = null;
            setDraggable(false);
        });
    });
});
