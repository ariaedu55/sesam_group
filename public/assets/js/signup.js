(function () {
    var form = document.querySelector("[data-signup-form]");
    if (!form) {
        return;
    }

    var steps = Array.prototype.slice.call(form.querySelectorAll("[data-signup-step]"));
    var navItems = Array.prototype.slice.call(form.querySelectorAll("[data-signup-nav] [data-step]"));
    var back = form.querySelector("[data-signup-back]");
    var next = form.querySelector("[data-signup-next]");
    var submitWrap = form.querySelector("[data-signup-submit]");
    var total = steps.length;
    if (total === 0) {
        return;
    }

    form.classList.add("is-wizard");
    var current = 0;

    function firstStepWithError() {
        for (var i = 0; i < steps.length; i++) {
            if (steps[i].querySelector(".is-invalid, .field-error")) {
                return i;
            }
        }
        return 0;
    }

    function errorNode(field) {
        var id = field.getAttribute("id") || field.getAttribute("name") || "field";
        return steps[current].querySelector("#" + id + "-error");
    }

    function show(index) {
        current = Math.max(0, Math.min(index, total - 1));
        steps.forEach(function (step, i) {
            var on = i === current;
            step.classList.toggle("is-current", on);
            step.hidden = !on;
        });
        navItems.forEach(function (item, i) {
            item.classList.toggle("is-current", i === current);
            item.classList.toggle("is-done", i < current);
            if (i === current) {
                item.setAttribute("aria-current", "step");
            } else {
                item.removeAttribute("aria-current");
            }
        });
        if (back) {
            back.hidden = current === 0;
        }
        if (next) {
            next.hidden = current === total - 1;
        }
        if (submitWrap) {
            submitWrap.hidden = current !== total - 1;
        }
        var focusable = steps[current].querySelector("input:not([type=hidden])");
        if (focusable && typeof focusable.focus === "function") {
            window.setTimeout(function () {
                focusable.focus();
            }, 30);
        }
    }

    function mark(field, message) {
        field.classList.add("is-invalid");
        field.setAttribute("aria-invalid", "true");
        var err = errorNode(field);
        if (!err) {
            err = document.createElement("p");
            err.className = "field-error";
            err.id = (field.getAttribute("id") || field.getAttribute("name") || "field") + "-error";
            err.setAttribute("data-client", "1");
            field.insertAdjacentElement("afterend", err);
        } else {
            err.setAttribute("data-client", "1");
        }
        err.textContent = message;
    }

    function clearMarks(step) {
        step.querySelectorAll(".is-invalid").forEach(function (el) {
            el.classList.remove("is-invalid");
            el.removeAttribute("aria-invalid");
        });
        step.querySelectorAll(".field-error[data-client]").forEach(function (el) {
            el.remove();
        });
    }

    function fail(field, message, state) {
        mark(field, message);
        if (!state.firstBad) {
            state.firstBad = field;
        }
        state.ok = false;
    }

    function validate() {
        var step = steps[current];
        clearMarks(step);
        var state = { ok: true, firstBad: null };

        if (current === 0) {
            var chosen = step.querySelector("input[name=sector]:checked");
            var radios = step.querySelector("input[name=sector]");
            if (!chosen && radios) {
                fail(radios, "Choisissez votre service.", state);
            }
        }

        if (current === 1) {
            var name = step.querySelector("#full_name");
            var phone = step.querySelector("#phone");
            if (name && name.value.trim().length < 2) {
                fail(name, "Écrivez votre nom.", state);
            }
            if (phone && !/^\+?[0-9][0-9\s.\-]{7,}$/.test(phone.value.trim())) {
                fail(phone, "Numéro invalide. Exemple : 77 123 45 67.", state);
            }
        }

        if (current === 2) {
            var email = step.querySelector("#email");
            var password = step.querySelector("#password");
            var confirm = step.querySelector("#password_confirm");
            var terms = step.querySelector("input[name=terms]");
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                fail(email, "E-mail invalide.", state);
            }
            if (password && password.value.length < 12) {
                fail(password, "12 caractères minimum.", state);
            }
            if (password && confirm && password.value !== confirm.value) {
                fail(confirm, "Les deux mots de passe ne sont pas identiques.", state);
            }
            if (terms && !terms.checked) {
                fail(terms, "Acceptez les conditions pour créer votre compte.", state);
            }
        }

        if (state.firstBad && typeof state.firstBad.focus === "function") {
            state.firstBad.focus();
        }
        return state.ok;
    }

    if (next) {
        next.addEventListener("click", function () {
            if (validate()) {
                show(current + 1);
            }
        });
    }
    if (back) {
        back.addEventListener("click", function () {
            show(current - 1);
        });
    }
    navItems.forEach(function (item, i) {
        item.addEventListener("click", function () {
            if (i === current) {
                return;
            }
            if (i < current) {
                show(i);
                return;
            }
            if (i === current + 1 && validate()) {
                show(i);
            }
        });
    });
    form.addEventListener("submit", function (event) {
        if (current !== total - 1) {
            event.preventDefault();
            if (validate()) {
                show(current + 1);
            }
            return;
        }
        if (!validate()) {
            event.preventDefault();
        }
    });

    show(firstStepWithError());
})();
