import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";
import PrimeVue from "primevue/config";
import RichTextEditor from "../RichTextEditor.vue";

const global = { plugins: [PrimeVue] };

describe("RichTextEditor", () => {
    it("renders the initial modelValue content", async () => {
        const wrapper = mount(RichTextEditor, {
            global,
            props: { modelValue: "<p>Bonjour</p>" },
        });
        await nextTick();

        expect(wrapper.text()).toContain("Bonjour");
    });

    it("emits update:modelValue with HTML when the toolbar toggles a mark", async () => {
        const wrapper = mount(RichTextEditor, {
            global,
            props: { modelValue: "<p>Texte</p>" },
        });
        await nextTick();

        await wrapper.find('[aria-label="Gras"]').trigger("click");
        await nextTick();

        const emitted = wrapper.emitted("update:modelValue");
        expect(emitted).toBeTruthy();
        expect((emitted![emitted!.length - 1][0] as string)).toContain("<p>");
    });

    it("shows the error message passed via the error prop", async () => {
        const wrapper = mount(RichTextEditor, {
            global,
            props: { modelValue: "", error: "Ce champ est requis." },
        });
        await nextTick();

        expect(wrapper.text()).toContain("Ce champ est requis.");
    });
});
