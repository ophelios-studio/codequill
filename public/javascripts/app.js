import Form from "./pulsar/modules/form.js";

export default class Application {

    #formEngine;
    #configurations;
    i18n;

    constructor(configurations) {
        this.#configurations = configurations;
        this.i18n = configurations.i18n;
    }

    initialize() {
        this.#initializeForms();
        this.#initializePeity();
    }

    getFormEngine() {
        return this.#formEngine;
    }

    #initializeForms() {
        this.#formEngine = new Form(this.i18n.buttons.loading);
        this.#formEngine.preventDoubleSubmission();
        this.#formEngine.autoResizeTextarea();
    }

    #initializePeity() {
        // https://github.com/railsjazz/peity_vanilla
        document.querySelectorAll(".peity-pie").forEach(e => peity(e, "pie"));
        document.querySelectorAll(".peity-donut").forEach(e => peity(e, "donut"));
        document.querySelectorAll(".peity-line").forEach(e => peity(e, "line"));
        document.querySelectorAll(".peity-bar").forEach(e => peity(e, "bar"));
    }
}
