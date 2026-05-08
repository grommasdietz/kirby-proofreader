<template>
  <div class="proofreader-rule-toggles">
    <label
      v-for="option in options"
      :key="option.name"
      class="k-button proofreader-rule-toggle"
      :data-selected="isSelected(option.name)"
      :data-disabled="option.count === 0"
      data-has-icon="true"
      data-has-text="true"
      data-theme="passive"
      :data-variant="isSelected(option.name) ? 'filled' : 'dimmed'"
      :aria-disabled="option.count === 0"
    >
      <input
        class="proofreader-rule-toggle-input"
        :checked="isSelected(option.name)"
        :disabled="option.count === 0"
        type="checkbox"
        :value="option.name"
        @change="toggle(option.name, $event.target.checked)"
      />
      <span class="k-button-icon proofreader-rule-toggle-icon" aria-hidden="true">
        <k-icon type="check" />
      </span>
      <span class="k-button-text proofreader-rule-toggle-text">
        {{ option.label }}
      </span>
      <span class="proofreader-rule-toggle-count">{{ option.count }}</span>
    </label>
  </div>
</template>

<script>
export default {
  name: "k-proofreader-rule-toggles",

  props: {
    options: {
      type: Array,
      default: () => [],
    },
    value: {
      type: Array,
      default: () => [],
    },
  },

  emits: ["input"],

  methods: {
    isSelected(name) {
      return this.value.includes(name);
    },
    toggle(name, selected) {
      if (selected === true && this.value.includes(name) === false) {
        this.$emit("input", [...this.value, name]);
        return;
      }

      if (selected === false) {
        this.$emit(
          "input",
          this.value.filter((item) => item !== name)
        );
      }
    },
  },
};
</script>
