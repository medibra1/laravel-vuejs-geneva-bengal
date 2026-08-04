<script setup lang="ts">
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';

const props = defineProps<{
    frHasError?: boolean;
    enHasError?: boolean;
}>();

// A validation error on the English side used to fail completely
// silently: the "en" TabPanel isn't rendered while "fr" is active, so
// its <InputError> never became visible and the form just looked like
// it did nothing on submit. Starting on whichever tab actually has an
// error (rather than always "fr") fixes that.
const initialTab = props.enHasError && !props.frHasError ? 'en' : 'fr';
</script>

<template>
    <Tabs :value="initialTab">
        <TabList>
            <Tab value="fr">
                Français
                <span
                    v-if="frHasError"
                    class="ml-1 inline-block h-1.5 w-1.5 rounded-full bg-red-500"
                    aria-label="Erreur"
                />
            </Tab>
            <Tab value="en">
                English
                <span
                    v-if="enHasError"
                    class="ml-1 inline-block h-1.5 w-1.5 rounded-full bg-red-500"
                    aria-label="Erreur"
                />
            </Tab>
        </TabList>
        <TabPanels>
            <TabPanel value="fr">
                <slot name="fr" />
            </TabPanel>
            <TabPanel value="en">
                <slot name="en" />
            </TabPanel>
        </TabPanels>
    </Tabs>
</template>
