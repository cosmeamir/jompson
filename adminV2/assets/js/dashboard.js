document.addEventListener('DOMContentLoaded', () => {
    const pageBody = document.body;
    const sidebarToggle = document.querySelector('[data-toggle-sidebar]');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    const defaultSection = 'overview';
    const sections = Array.from(document.querySelectorAll('[data-section]'));
    const navLinks = Array.from(document.querySelectorAll('[data-section-target]'));

    const closeSidebar = () => {
        pageBody.classList.remove('sidebar-open');
    };

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', (event) => {
            event.preventDefault();
            pageBody.classList.toggle('sidebar-open');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    const activateSection = (id) => {
        if (!id) {
            id = defaultSection;
        }
        let found = false;
        sections.forEach((section) => {
            const match = section.id === id;
            section.classList.toggle('is-active', match);
            if (match) {
                found = true;
            }
        });
        navLinks.forEach((link) => {
            const target = link.getAttribute('data-section-target');
            const isActive = target === id;
            link.classList.toggle('is-active', isActive);
        });
        if (!found && sections.length > 0) {
            activateSection(defaultSection);
        }
    };

    const parseHash = () => {
        const raw = window.location.hash.replace('#', '').trim();
        if (!raw) {
            return defaultSection;
        }
        return raw;
    };

    navLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            const target = link.getAttribute('data-section-target');
            if (!target) {
                return;
            }
            event.preventDefault();
            if (window.location.hash !== `#${target}`) {
                window.location.hash = `#${target}`;
            }
            activateSection(target);
            closeSidebar();
        });
    });

    activateSection(parseHash());
    window.addEventListener('hashchange', () => activateSection(parseHash()));

    const courseForm = document.getElementById('course-form');
    if (!courseForm) {
        return;
    }

    const modeInput = document.getElementById('course-mode');
    const idInput = document.getElementById('course-id');
    const helperText = document.getElementById('course-form-helper');
    const formTitle = document.getElementById('course-form-title');
    const submitButton = document.getElementById('course-submit');
    const resetButton = document.getElementById('course-reset');
    const categorySelect = document.getElementById('course-category');
    const subcategorySelect = document.getElementById('course-subcategory');

    const dashboardData = window.dashboardData || {};
    let categoriesData = Array.isArray(dashboardData.categories) ? dashboardData.categories.slice() : [];
    let subcategoriesData = Array.isArray(dashboardData.subcategories) ? dashboardData.subcategories.slice() : [];

    const textFields = {
        title: document.getElementById('course-title'),
        headline: document.getElementById('course-headline'),
        price: document.getElementById('course-price'),
        overview: document.getElementById('course-overview'),
        general_objectives: document.getElementById('course-general-objectives'),
        specific_objectives: document.getElementById('course-specific-objectives'),
        contents: document.getElementById('course-contents'),
        details: document.getElementById('course-details'),
        pdf_url: document.getElementById('course-pdf')
    };

    const sortByName = (a, b) => (a.name || '').localeCompare(b.name || '', 'pt', { sensitivity: 'base' });

    categoriesData.sort(sortByName);
    subcategoriesData.sort((a, b) => {
        const categoryCompare = (a.category_id || '').localeCompare(b.category_id || '', 'pt', { sensitivity: 'base' });
        if (categoryCompare !== 0) {
            return categoryCompare;
        }
        return sortByName(a, b);
    });

    const renderCategoryOptions = (selectedId = '') => {
        if (!categorySelect) {
            return;
        }

        categorySelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = categoriesData.length ? 'Selecciona uma categoria' : 'Nenhuma categoria disponível';
        placeholder.disabled = categoriesData.length === 0;
        placeholder.selected = true;
        categorySelect.appendChild(placeholder);

        categoriesData.forEach((category) => {
            const option = document.createElement('option');
            option.value = category.id || '';
            option.textContent = category.name || '—';
            if (category.id === selectedId) {
                option.selected = true;
                placeholder.selected = false;
            }
            categorySelect.appendChild(option);
        });

        categorySelect.disabled = categoriesData.length === 0;
        if (selectedId && categorySelect.value !== selectedId) {
            categorySelect.value = selectedId;
        }
    };

    const renderSubcategoryOptions = (categoryId = '', selectedId = '') => {
        if (!subcategorySelect) {
            return;
        }

        subcategorySelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = categoryId ? 'Selecciona uma subcategoria' : 'Selecciona uma categoria primeiro';
        placeholder.disabled = !!categoryId;
        placeholder.selected = true;
        subcategorySelect.appendChild(placeholder);

        if (!categoryId) {
            subcategorySelect.disabled = true;
            return;
        }

        const filtered = subcategoriesData.filter((item) => (item.category_id || '') === categoryId);
        filtered.forEach((subcategory) => {
            const option = document.createElement('option');
            option.value = subcategory.id || '';
            option.textContent = subcategory.name || '—';
            if (subcategory.id === selectedId) {
                option.selected = true;
                placeholder.selected = false;
            }
            subcategorySelect.appendChild(option);
        });

        subcategorySelect.disabled = filtered.length === 0;
        if (selectedId && subcategorySelect.value !== selectedId) {
            subcategorySelect.value = selectedId;
        }
    };

    const setMode = (mode) => {
        const isUpdate = mode === 'update';
        if (modeInput) {
            modeInput.value = isUpdate ? 'update' : 'create';
        }
        if (submitButton) {
            submitButton.textContent = isUpdate ? 'Actualizar curso' : 'Guardar curso';
        }
        if (helperText) {
            helperText.textContent = isUpdate
                ? 'Edita a informação do curso seleccionado e guarda para actualizar no site.'
                : 'Preenche os campos para adicionar um novo curso ao catálogo.';
        }
        if (formTitle) {
            formTitle.textContent = isUpdate ? 'Editar curso' : 'Adicionar curso';
        }
        if (!isUpdate && idInput) {
            idInput.value = '';
        }
    };

    renderCategoryOptions('');
    renderSubcategoryOptions('', '');
    setMode(modeInput ? modeInput.value : 'create');

    if (categorySelect) {
        categorySelect.addEventListener('change', () => {
            renderSubcategoryOptions(categorySelect.value, '');
        });
    }

    if (resetButton) {
        resetButton.addEventListener('click', () => {
            courseForm.reset();
            setMode('create');
            renderCategoryOptions('');
            renderSubcategoryOptions('', '');
            if (categorySelect && !categorySelect.disabled) {
                categorySelect.focus();
            }
        });
    }

    document.querySelectorAll('[data-course]').forEach((button) => {
        button.addEventListener('click', () => {
            const payload = button.getAttribute('data-course');
            if (!payload) {
                return;
            }
            try {
                const course = JSON.parse(payload);
                setMode('update');
                if (idInput) {
                    idInput.value = course.id || '';
                }
                const categoryId = course.category_id || '';
                renderCategoryOptions(categoryId);
                renderSubcategoryOptions(categoryId, course.subcategory_id || '');
                Object.entries(textFields).forEach(([key, field]) => {
                    if (!field) {
                        return;
                    }
                    field.value = course[key] ? course[key] : '';
                });
                if (categorySelect && !categorySelect.disabled) {
                    categorySelect.focus();
                }
                window.location.hash = '#courses';
                activateSection('courses');
            } catch (error) {
                console.error('Não foi possível carregar os dados do curso seleccionado.', error);
            }
        });
    });
});
