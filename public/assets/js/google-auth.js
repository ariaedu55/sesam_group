(function () {
    var btn = document.querySelector("[data-google-signin]");
    var form = document.querySelector("[data-google-form]");
    var cfgEl = document.querySelector("[data-firebase-auth]");
    if (!btn || !form || !cfgEl) {
        return;
    }

    function config() {
        try {
            return JSON.parse(cfgEl.getAttribute("data-firebase-auth") || "{}");
        } catch (err) {
            return {};
        }
    }

    function fail(message) {
        window.alert(message);
        btn.disabled = false;
    }

    btn.addEventListener("click", function () {
        if (typeof firebase === "undefined" || !firebase.auth) {
            fail("Google n'a pas pu s'ouvrir. Rechargez la page.");
            return;
        }
        btn.disabled = true;
        var cfg = config();
        if (!firebase.apps.length) {
            firebase.initializeApp(cfg);
        }
        var provider = new firebase.auth.GoogleAuthProvider();
        provider.setCustomParameters({ prompt: "select_account" });
        provider.addScope("email");
        provider.addScope("profile");
        firebase.auth().signInWithPopup(provider).then(function (result) {
            if (!result || !result.user) {
                throw new Error("empty");
            }
            return result.user.getIdToken();
        }).then(function (token) {
            var field = form.querySelector("[name=id_token]");
            if (!field) {
                throw new Error("empty");
            }
            field.value = token;
            form.submit();
        }).catch(function (err) {
            btn.disabled = false;
            if (err && (err.code === "auth/popup-closed-by-user" || err.code === "auth/cancelled-popup-request")) {
                return;
            }
            fail("Connexion Google impossible. Vérifiez que le fournisseur Google est activé dans Firebase.");
        });
    });
})();
