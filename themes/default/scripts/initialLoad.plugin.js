(function (sceditor) {
    'use strict';

    // Core Editor startup options, css, smiley box, validate wizzy, move into view when needed
    sceditor.plugins.initialLoad = function() {
        let base = this;
        const isEditorLoaded = async selector => {
            while (document.querySelector(selector) === null) {
                await new Promise(resolve =>requestAnimationFrame(resolve));
            }
            return true;
        };

        base.signalReady = function() {
            let editor = this;

            // signalReady can be called before the extensionMethods are available
            isEditorLoaded('.sceditor-toolbar').then((selector) => {
                editor.createPermanentDropDown();
                editor.css("code {white-space: pre;}");
            });

            if (sceditor.isWysiwygSupported === false)
            {
                document.querySelectorAll(".sceditor-button-source").forEach((elem) => {elem.style.display = "none";});
            }

            // Move the editor into view
            if (document.getElementById("dropdown_menu_1") !== null || document.getElementById("preview_section") !== null)
            {
                // Do not scroll this menu off-screen when present
                document.getElementById("skipnav").scrollIntoView();
            }
        };
    };
}(sceditor));
