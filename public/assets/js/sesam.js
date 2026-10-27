(function () {
    var root = document.documentElement;
    root.classList.add("js");

    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var nav = document.querySelector("[data-nav]");
    var navToggle = document.querySelector("[data-nav-toggle]");
    if (nav && navToggle) {
        var closeNav = function () {
            nav.classList.remove("is-open");
            navToggle.setAttribute("aria-expanded", "false");
        };

        navToggle.addEventListener("click", function () {
            var open = nav.classList.toggle("is-open");
            navToggle.setAttribute("aria-expanded", open ? "true" : "false");
        });

        nav.addEventListener("click", function (event) {
            if (event.target.closest("a")) {
                closeNav();
            }
        });

        document.addEventListener("click", function (event) {
            if (!nav.contains(event.target) && !navToggle.contains(event.target)) {
                closeNav();
            }
        });

        window.addEventListener("scroll", function () {
            if (nav.classList.contains("is-open")) {
                closeNav();
            }
        }, { passive: true });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                closeNav();
            }
        });
    }

    var sidebar = document.querySelector("[data-sidebar]");
    var sidebarToggle = document.querySelector("[data-sidebar-toggle]");
    var sidebarClose = document.querySelector("[data-sidebar-close]");
    var sidebarScrim = document.querySelector("[data-sidebar-scrim]");
    if (sidebar && sidebarToggle) {
        var isSidebarDrawer = function () {
            return window.matchMedia("(max-width: 767px)").matches;
        };

        var setSidebarOpen = function (open) {
            if (!isSidebarDrawer()) {
                open = false;
            }
            sidebar.classList.toggle("is-open", open);
            sidebarToggle.setAttribute("aria-expanded", open ? "true" : "false");
            sidebarToggle.setAttribute("aria-label", open ? "Fermer le menu" : "Ouvrir le menu");
            document.body.classList.toggle("is-tenant-nav-open", open && document.body.classList.contains("is-app"));
            if (isSidebarDrawer()) {
                sidebar.setAttribute("aria-hidden", open ? "false" : "true");
                sidebar.inert = !open;
            } else {
                sidebar.removeAttribute("aria-hidden");
                sidebar.inert = false;
            }
            if (sidebarScrim) {
                sidebarScrim.toggleAttribute("hidden", !open);
            }
        };

        sidebarToggle.addEventListener("click", function () {
            setSidebarOpen(!sidebar.classList.contains("is-open"));
        });

        if (sidebarClose) {
            sidebarClose.addEventListener("click", function () {
                setSidebarOpen(false);
            });
        }

        if (sidebarScrim) {
            sidebarScrim.addEventListener("click", function () {
                setSidebarOpen(false);
            });
        }

        sidebar.addEventListener("click", function (event) {
            if (event.target.closest("a")) {
                setSidebarOpen(false);
            }
        });

        document.addEventListener("click", function (event) {
            if (!sidebar.classList.contains("is-open")) {
                return;
            }
            if (sidebar.contains(event.target) || sidebarToggle.contains(event.target)) {
                return;
            }
            setSidebarOpen(false);
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && sidebar.classList.contains("is-open")) {
                setSidebarOpen(false);
            }
        });

        window.matchMedia("(max-width: 767px)").addEventListener("change", function () {
            setSidebarOpen(false);
        });

        setSidebarOpen(false);
    }

    document.querySelectorAll("[data-dropdown]").forEach(function (dropdown) {
        var button = dropdown.querySelector("[data-dropdown-toggle]");
        var menu = dropdown.querySelector("[data-dropdown-menu]");
        if (!button || !menu) {
            return;
        }
        button.addEventListener("click", function () {
            var open = menu.hasAttribute("hidden");
            menu.toggleAttribute("hidden", !open);
            button.setAttribute("aria-expanded", open ? "true" : "false");
        });
    });

    document.addEventListener("click", function (event) {
        document.querySelectorAll("[data-dropdown]").forEach(function (dropdown) {
            if (dropdown.contains(event.target)) {
                return;
            }
            var button = dropdown.querySelector("[data-dropdown-toggle]");
            var menu = dropdown.querySelector("[data-dropdown-menu]");
            if (button && menu) {
                menu.setAttribute("hidden", "");
                button.setAttribute("aria-expanded", "false");
            }
        });
    });

    document.querySelectorAll("[data-open-dialog]").forEach(function (button) {
        button.addEventListener("click", function () {
            var dialog = document.getElementById(button.getAttribute("data-open-dialog"));
            if (dialog && typeof dialog.showModal === "function") {
                dialog.showModal();
            }
        });
    });

    var bindConfirm = function (scope) {
        scope.querySelectorAll("form[data-confirm]").forEach(function (form) {
            form.addEventListener("submit", function (event) {
                if (!window.confirm(form.getAttribute("data-confirm"))) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            });
        });
    };
    bindConfirm(document);

    document.querySelectorAll("[data-close-dialog]").forEach(function (button) {
        button.addEventListener("click", function () {
            var dialog = button.closest("dialog");
            if (dialog) {
                dialog.close();
            }
        });
    });

    if (!reduce) {
        var lastY = window.scrollY;
        var setScrollDir = function () {
            var y = window.scrollY;
            root.classList.toggle("scroll-up", y < lastY);
            root.classList.toggle("scroll-down", y >= lastY);
            lastY = y;
        };
        setScrollDir();
        window.addEventListener("scroll", setScrollDir, { passive: true });

        var revealNodes = Array.prototype.slice.call(document.querySelectorAll("[data-reveal]"));
        if ("IntersectionObserver" in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    entry.target.classList.toggle("is-visible", entry.isIntersecting);
                });
            }, { threshold: 0.16, rootMargin: "0px 0px -6% 0px" });
            revealNodes.forEach(function (node) {
                observer.observe(node);
            });
        } else {
            revealNodes.forEach(function (node) {
                node.classList.add("is-visible");
            });
        }

        document.querySelectorAll("[data-count]").forEach(function (node) {
            var target = Number(node.getAttribute("data-count"));
            if (!Number.isFinite(target)) {
                return;
            }
            var played = false;
            var run = function () {
                if (played) {
                    return;
                }
                played = true;
                var started = performance.now();
                var duration = 700;
                function frame(now) {
                    var progress = Math.min(1, (now - started) / duration);
                    node.textContent = String(Math.round(target * progress));
                    if (progress < 1) {
                        requestAnimationFrame(frame);
                    }
                }
                requestAnimationFrame(frame);
            };
            if ("IntersectionObserver" in window) {
                var counterObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            run();
                            counterObserver.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.4 });
                counterObserver.observe(node);
            } else {
                run();
            }
        });
    }

    document.querySelectorAll("[data-agenda]").forEach(function (agenda) {
        var pick = agenda.querySelector("[data-agenda-pick]");
        var groups = [
            agenda.querySelectorAll("[data-agenda-service]"),
            agenda.querySelectorAll("[data-agenda-day]"),
            agenda.querySelectorAll("[data-agenda-slot]")
        ];
        groups.forEach(function (buttons) {
            buttons.forEach(function (button) {
                button.addEventListener("click", function () {
                    buttons.forEach(function (other) {
                        var on = other === button;
                        other.classList.toggle("is-on", on);
                        other.setAttribute("aria-pressed", on ? "true" : "false");
                    });
                    if (pick && button.hasAttribute("data-agenda-slot")) {
                        pick.textContent = button.firstChild.textContent.trim();
                    }
                });
            });
        });
    });

    document.querySelectorAll("[data-offer-tour]").forEach(function (stack) {
        var tiles = Array.prototype.slice.call(stack.querySelectorAll(".offer-tile"));
        if (!tiles.length) {
            return;
        }
        if (reduce) {
            return;
        }
        var played = false;
        var run = function () {
            if (played) {
                return;
            }
            played = true;
            var i = 0;
            var tick = function () {
                tiles.forEach(function (tile, index) {
                    tile.classList.toggle("is-lit", index === i);
                });
                i += 1;
                if (i < tiles.length) {
                    window.setTimeout(tick, 700);
                } else {
                    tiles.forEach(function (tile, index) {
                        tile.classList.toggle("is-lit", index === 1);
                    });
                }
            };
            tick();
        };
        var panel = stack.closest(".service-panel");
        if (panel && "IntersectionObserver" in window) {
            var offerSpy = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        run();
                        offerSpy.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.35 });
            offerSpy.observe(panel);
        }
    });

    var spySections = Array.prototype.slice.call(document.querySelectorAll("section[data-service]"));
    var orbitRoot = document.querySelector(".landing-orbit");
    var orbitNodes = orbitRoot ? Array.prototype.slice.call(orbitRoot.querySelectorAll(".orbit-node[data-service]")) : [];
    var orbitLines = orbitRoot ? Array.prototype.slice.call(orbitRoot.querySelectorAll(".orbit-lines line")) : [];
    var tourIndex = 0;
    var tourPaused = false;

    var landingActive = function () {
        return window.scrollY < 180;
    };

    var setOn = function (key) {
        orbitNodes.forEach(function (node) {
            node.classList.toggle("is-on", node.getAttribute("data-service") === key);
            node.classList.remove("is-current");
        });
        orbitLines.forEach(function (line, index) {
            var node = orbitNodes[index];
            line.classList.toggle("is-live", !!(node && node.getAttribute("data-service") === key));
        });
    };

    var tourTick = function () {
        if (tourPaused || !landingActive() || !orbitNodes.length) {
            return;
        }
        setOn(orbitNodes[tourIndex].getAttribute("data-service"));
        tourIndex = (tourIndex + 1) % orbitNodes.length;
    };

    if (orbitNodes.length) {
        setOn(orbitNodes[0].getAttribute("data-service"));
        if (!reduce) {
            tourIndex = 1;
            window.setInterval(tourTick, 1000);
            orbitNodes.forEach(function (node, index) {
                node.addEventListener("pointerenter", function () {
                    tourPaused = true;
                    tourIndex = (index + 1) % orbitNodes.length;
                    setOn(node.getAttribute("data-service"));
                });
                node.addEventListener("pointerleave", function () {
                    tourPaused = false;
                });
                node.addEventListener("focus", function () {
                    tourPaused = true;
                    setOn(node.getAttribute("data-service"));
                });
                node.addEventListener("blur", function () {
                    tourPaused = false;
                });
            });
        }
    }

    if (orbitNodes.length && spySections.length && "IntersectionObserver" in window) {
        var spy = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && !landingActive()) {
                    tourPaused = true;
                    setOn(entry.target.getAttribute("data-service"));
                }
            });
        }, { threshold: 0.45 });
        spySections.forEach(function (section) {
            spy.observe(section);
        });
        window.addEventListener("scroll", function () {
            if (landingActive() && !reduce) {
                tourPaused = false;
            }
        }, { passive: true });
    }

    var bindImagePreview = function (scope) {
        scope.querySelectorAll("[data-image-preview]").forEach(function (input) {
            var wrap = input.closest("label");
            var target = wrap ? wrap.querySelector("[data-image-preview-target]") : null;
            if (!target) {
                return;
            }
            input.addEventListener("change", function () {
                var file = input.files && input.files[0];
                target.replaceChildren();
                if (!file || !file.type || file.type.indexOf("image/") !== 0) {
                    target.hidden = true;
                    return;
                }
                var image = document.createElement("img");
                image.alt = "";
                image.width = 120;
                image.height = 120;
                image.src = URL.createObjectURL(file);
                target.appendChild(image);
                target.hidden = false;
            });
        });
    };
    bindImagePreview(document);

    document.addEventListener("click", function (event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        var row = event.target.closest("[data-row-href]");
        if (!row || event.target.closest("a, button, input, select, textarea, label")) {
            return;
        }
        window.location.assign(row.getAttribute("data-row-href"));
    });

    document.addEventListener("keydown", function (event) {
        if (event.key !== "Enter" && event.key !== " ") {
            return;
        }
        var row = event.target.closest("[data-row-href]");
        if (!row || event.target.closest("a, button, input, select, textarea")) {
            return;
        }
        event.preventDefault();
        window.location.assign(row.getAttribute("data-row-href"));
    });

    document.querySelectorAll("form[data-live-filter]").forEach(function (form) {
        var targetSelector = form.getAttribute("data-live-filter-target") || "";
        var target = targetSelector ? document.querySelector(targetSelector) : null;
        if (!target) {
            return;
        }
        var delay = Number(form.getAttribute("data-live-filter-delay") || 280);
        if (!Number.isFinite(delay) || delay < 0) {
            delay = 280;
        }
        var searchField = form.querySelector('[name="q"]');
        var timer = null;
        var controller = null;
        var buildUrl = function (extraParams) {
            var params = new URLSearchParams(new FormData(form));
            if (extraParams) {
                Object.keys(extraParams).forEach(function (key) {
                    if (extraParams[key] === null || extraParams[key] === "") {
                        params.delete(key);
                    } else {
                        params.set(key, extraParams[key]);
                    }
                });
            }
            params.delete("page");
            params.forEach(function (value, key) {
                if (value === "") {
                    params.delete(key);
                }
            });
            var query = params.toString();
            return form.action + (query ? "?" + query : "");
        };

        var load = function (url) {
            if (controller && typeof controller.abort === "function") {
                controller.abort();
            }
            controller = typeof AbortController === "function" ? new AbortController() : null;
            target.classList.add("is-loading");
            target.setAttribute("aria-busy", "true");
            fetch(url, {
                credentials: "same-origin",
                signal: controller ? controller.signal : undefined,
                headers: { "X-Sesam-Partial": "1" }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error("filter");
                    }
                    return response.text();
                })
                .then(function (html) {
                    target.innerHTML = html;
                    if (window.history && typeof window.history.replaceState === "function") {
                        window.history.replaceState(null, "", url);
                    }
                })
                .catch(function (error) {
                    if (error && error.name === "AbortError") {
                        return;
                    }
                    form.submit();
                })
                .then(function () {
                    target.classList.remove("is-loading");
                    target.removeAttribute("aria-busy");
                });
        };

        var schedule = function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                load(buildUrl());
            }, delay);
        };

        if (searchField) {
            searchField.addEventListener("input", schedule);
            searchField.addEventListener("search", function () {
                if (searchField.value === "") {
                    schedule();
                }
            });
        }

        form.querySelectorAll("select").forEach(function (select) {
            select.addEventListener("change", function () {
                load(buildUrl());
            });
        });

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            window.clearTimeout(timer);
            load(buildUrl());
        });

        target.addEventListener("click", function (event) {
            var link = event.target.closest("a[href]");
            if (!link || !target.contains(link) || !link.closest("[data-live-filter-pages]")) {
                return;
            }
            event.preventDefault();
            load(link.href);
        });
    });

    /*
     * Formulaires en modale plein écran (espaces commerce et SESAM).
     * a[data-modal] : la page du formulaire est chargée puis affichée dans la modale.
     * form[data-modal-form] : un formulaire de la page est remplacé par un bouton qui l'ouvre en modale.
     * Sans JavaScript, les liens et formulaires restent des pages et des formulaires classiques.
     */
    if (!document.body.classList.contains("is-app") || typeof HTMLDialogElement === "undefined") {
        return;
    }

    var modal = document.createElement("dialog");
    modal.className = "form-modal";
    modal.setAttribute("aria-labelledby", "form-modal-title");
    modal.innerHTML =
        '<div class="form-modal-panel">' +
        '<header class="form-modal-head">' +
        '<p class="form-modal-kicker" data-modal-kicker></p>' +
        '<h2 class="form-modal-title" id="form-modal-title"></h2>' +
        '<button class="form-modal-close" type="button" data-modal-close aria-label="Fermer">' +
        '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>' +
        "</button>" +
        "</header>" +
        '<div class="form-modal-body" data-modal-body></div>' +
        "</div>";
    document.body.appendChild(modal);

    var modalBody = modal.querySelector("[data-modal-body]");
    var modalTitle = modal.querySelector("#form-modal-title");
    var modalKicker = modal.querySelector("[data-modal-kicker]");
    var modalOrigin = null;
    var modalRemote = false;
    var modalBusy = false;

    var closeModal = function () {
        if (modal.open) {
            modal.close();
        }
    };

    modal.addEventListener("close", function () {
        modalBody.replaceChildren();
        modalRemote = false;
        document.documentElement.classList.remove("has-form-modal");
        if (modalOrigin && document.contains(modalOrigin)) {
            modalOrigin.focus();
        }
        modalOrigin = null;
    });

    modal.addEventListener("click", function (event) {
        if (event.target === modal || event.target.closest("[data-modal-close]")) {
            closeModal();
            return;
        }
        var link = event.target.closest("a[href]");
        if (link && modalBody.contains(link)) {
            var target = new URL(link.href, window.location.href);
            if (target.origin === window.location.origin && target.pathname === window.location.pathname && !target.hash) {
                event.preventDefault();
                closeModal();
            }
        }
    });

    var openModal = function (title, kicker) {
        modalTitle.textContent = title || "Formulaire";
        modalKicker.textContent = kicker || "";
        modalKicker.hidden = !kicker;
        document.documentElement.classList.add("has-form-modal");
        if (!modal.open) {
            modal.showModal();
        }
        var first = modalBody.querySelector("input:not([type=hidden]):not([disabled]), select, textarea");
        if (first) {
            first.focus({ preventScroll: true });
        } else {
            modal.querySelector("[data-modal-close]").focus();
        }
    };

    var extractPage = function (html) {
        var page = new DOMParser().parseFromString(html, "text/html");
        var main = page.querySelector("main.content");
        if (!main || !main.querySelector("form")) {
            return null;
        }
        main.querySelectorAll(":scope > .alert[role=status]").forEach(function (node) {
            node.remove();
        });
        var heading = main.querySelector("h1");
        var title = heading ? heading.textContent.trim() : (page.title || "");
        if (heading) {
            heading.remove();
        }
        var fragment = document.createDocumentFragment();
        Array.prototype.slice.call(main.childNodes).forEach(function (node) {
            fragment.appendChild(document.importNode(node, true));
        });
        return { title: title, fragment: fragment };
    };

    var showLoading = function () {
        modalBody.replaceChildren();
        var loading = document.createElement("div");
        loading.className = "form-modal-loading";
        loading.setAttribute("role", "status");
        loading.innerHTML = '<span class="skeleton-line"></span><span class="skeleton-line skeleton-short"></span><span class="skeleton-block"></span>';
        modalBody.appendChild(loading);
    };

    var showFailure = function (message) {
        modalBody.replaceChildren();
        var alert = document.createElement("p");
        alert.className = "alert alert-danger";
        alert.setAttribute("role", "alert");
        alert.textContent = message;
        modalBody.appendChild(alert);
    };

    var render = function (page) {
        modalBody.replaceChildren(page.fragment);
        bindConfirm(modalBody);
        bindImagePreview(modalBody);
        modalTitle.textContent = page.title || modalTitle.textContent;
        var invalid = modalBody.querySelector(".field-error, .is-invalid");
        var focusTarget = invalid ? invalid.closest(".field") : null;
        var field = focusTarget ? focusTarget.querySelector("input, select, textarea") : modalBody.querySelector("input:not([type=hidden]):not([disabled]), select, textarea");
        if (field) {
            field.focus({ preventScroll: true });
        }
    };

    var loadRemote = function (url, kicker) {
        modalRemote = true;
        showLoading();
        openModal("Chargement…", kicker);
        fetch(url, { credentials: "same-origin", headers: { "X-Sesam-Modal": "1" } })
            .then(function (response) {
                if (response.redirected || !response.ok) {
                    window.location.href = url;
                    return null;
                }
                return response.text();
            })
            .then(function (html) {
                if (html === null) {
                    return;
                }
                var page = extractPage(html);
                if (!page) {
                    window.location.href = url;
                    return;
                }
                render(page);
            })
            .catch(function () {
                showFailure("Le formulaire n'a pas pu être chargé. Vérifiez la connexion puis réessayez.");
            });
    };

    var submitInModal = function (form, submitter) {
        if (modalBusy) {
            return;
        }
        modalBusy = true;
        var data = new FormData(form);
        if (submitter && submitter.name) {
            data.append(submitter.name, submitter.value);
        }
        var buttons = form.querySelectorAll("button[type=submit], button:not([type])");
        buttons.forEach(function (button) {
            button.disabled = true;
            button.setAttribute("aria-busy", "true");
        });
        modal.classList.add("is-busy");
        fetch(form.action, {
            method: "POST",
            body: data,
            credentials: "same-origin",
            headers: { "X-Sesam-Modal": "1" }
        })
            .then(function (response) {
                var next = response.headers.get("X-Sesam-Location");
                if (next) {
                    window.location.assign(next);
                    return null;
                }
                return response.text().then(function (html) {
                    return { status: response.status, html: html };
                });
            })
            .then(function (result) {
                if (result === null) {
                    return;
                }
                var page = result.status === 200 || result.status === 422 ? extractPage(result.html) : null;
                if (page) {
                    render(page);
                    return;
                }
                if (result.status === 419) {
                    showFailure("La session a expiré. Rechargez la page puis recommencez.");
                    return;
                }
                if (result.status === 403) {
                    showFailure("Vous n'avez pas l'autorisation d'effectuer cette action.");
                    return;
                }
                if (result.status === 429) {
                    showFailure("Trop de tentatives. Patientez quelques minutes.");
                    return;
                }
                showFailure("L'enregistrement n'a pas abouti. Rechargez la page puis réessayez.");
            })
            .catch(function () {
                showFailure("Connexion interrompue. Vérifiez le réseau puis réessayez.");
            })
            .then(function () {
                modalBusy = false;
                modal.classList.remove("is-busy");
                buttons.forEach(function (button) {
                    button.disabled = false;
                    button.removeAttribute("aria-busy");
                });
            });
    };

    modal.addEventListener("submit", function (event) {
        var form = event.target;
        if (!modalRemote || event.defaultPrevented || !(form instanceof HTMLFormElement)) {
            return;
        }
        if ((form.getAttribute("method") || "get").toLowerCase() !== "post") {
            return;
        }
        event.preventDefault();
        submitInModal(form, event.submitter || null);
    });

    document.addEventListener("click", function (event) {
        var link = event.target.closest("a[data-modal]");
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        if (link.pathname === window.location.pathname) {
            return;
        }
        event.preventDefault();
        modalOrigin = link;
        loadRemote(link.href, link.getAttribute("data-modal") || "");
    });

    document.querySelectorAll("form[data-modal-form]").forEach(function (form) {
        var label = form.getAttribute("data-modal-form") || "Modifier";
        var title = form.getAttribute("data-modal-title") || label;
        var trigger = document.createElement("button");
        trigger.type = "button";
        trigger.className = "btn btn-secondary btn-sm form-modal-trigger";
        trigger.innerHTML = '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/></svg>';
        trigger.appendChild(document.createTextNode(label));
        var open = function () {
            modalOrigin = trigger;
            modalRemote = false;
            modalBody.replaceChildren(form);
            openModal(title, form.getAttribute("data-modal-kicker") || "");
        };
        trigger.addEventListener("click", open);
        form.replaceWith(trigger);
        if (form.querySelector(".field-error, .is-invalid")) {
            open();
        }
    });
}());
