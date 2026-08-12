(function() {
    'use strict';

    var footer = document.getElementById('prayer-pop-submissions-footer');
    var submissionsForm = document.getElementById('posts-filter');

    if (!footer || !submissionsForm || !submissionsForm.parentNode) {
        return;
    }

    submissionsForm.insertAdjacentElement('afterend', footer);
    footer.hidden = false;
}());
