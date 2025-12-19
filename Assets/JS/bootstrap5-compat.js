(function ($, bootstrap) {
    if (!$ || !bootstrap) {
        return;
    }

    function ensureInstance(Constructor, element, config) {
        let instance = Constructor.getInstance(element);
        if (!instance) {
            instance = Constructor.getOrCreateInstance(element, config);
        }
        return instance;
    }

    function bridge(pluginName, Constructor) {
        if (!Constructor || typeof $.fn[pluginName] === 'function') {
            return;
        }

        $.fn[pluginName] = function (option, ...args) {
            return this.each(function () {
                const isObjectArg = typeof option === 'object' && option !== null;
                const config = isObjectArg ? option : {};
                const instance = ensureInstance(Constructor, this, config);

                if (typeof option === 'string') {
                    const method = instance && instance[option];
                    if (typeof method === 'function') {
                        method.apply(instance, args);
                    }
                } else if (pluginName === 'toast') {
                    if (config.show || option === undefined) {
                        instance.show();
                    }
                } else {
                    if (config.show || option === undefined) {
                        instance.show();
                    }
                }
            });
        };
    }

    bridge('modal', bootstrap.Modal);
    bridge('toast', bootstrap.Toast);
})(window.jQuery, window.bootstrap);
