import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import ApplicationLogo from "../ApplicationLogo.vue";

describe("ApplicationLogo", () => {
    it("renders", () => {
        const wrapper = mount(ApplicationLogo);
        expect(wrapper.exists()).toBe(true);
    });
});
