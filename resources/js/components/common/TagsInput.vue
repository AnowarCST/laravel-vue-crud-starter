<template>
    <div>
        <div v-if="tags.length" class="d-flex flex-wrap gap-1 mb-2">
            <span
                v-for="(tag, index) in tags"
                :key="tag.text"
                class="badge text-bg-secondary d-inline-flex align-items-center"
            >
                {{ tag.text }}
                <button
                    type="button"
                    class="btn-close btn-close-white ms-1 tag-remove"
                    :aria-label="`Remove tag ${tag.text}`"
                    @click="remove(index)"
                ></button>
            </span>
        </div>
        <div class="input-group">
            <input
                :id="id"
                :value="modelValue"
                class="form-control"
                :class="{ 'is-invalid': invalid }"
                :placeholder="placeholder"
                :list="`${id}-suggestions`"
                maxlength="191"
                autocomplete="off"
                @input="$emit('update:modelValue', $event.target.value)"
                @keydown.enter.prevent="add"
                @keydown.,.prevent="add"
            />
            <button type="button" class="btn btn-outline-secondary" @click="add">Add</button>
        </div>
        <datalist :id="`${id}-suggestions`">
            <option v-for="item in autocompleteItems" :key="item.text" :value="item.text"></option>
        </datalist>
    </div>
</template>

<script setup>
// Replacement for @johmun/vue-tags-input. Emits tags as [{ text }].
const props = defineProps({
    id: { type: String, default: 'tags-input' },
    modelValue: { type: String, default: '' },
    tags: { type: Array, default: () => [] },
    autocompleteItems: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Type a tag and press Enter' },
    invalid: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'tags-changed']);

function add() {
    const text = props.modelValue.trim();
    if (text && !props.tags.some((tag) => tag.text.toLowerCase() === text.toLowerCase())) {
        emit('tags-changed', [...props.tags, { text }]);
    }
    emit('update:modelValue', '');
}

function remove(index) {
    emit(
        'tags-changed',
        props.tags.filter((_, i) => i !== index),
    );
}
</script>
