export const siteMeta = {
  name: "Автошкола Курсант +",
  shortName: "Курсант +",
  description:
    "Автошкола категории B в Москве: обучение на МКПП и АКПП, внимательная подготовка аккуратных водителей, сопровождение до экзамена в ГИБДД и дополнительные занятия после получения прав.",
  city: "Москва",
  heroTitle: "Подготовка водителей категории B",
  heroText:
    "МКПП и АКПП, три программы обучения, спокойная и понятная подготовка внимательных водителей и сопровождение ученика от первой консультации до экзамена в ГИБДД.",
  priceFrom: "39 000 ₽",
  priceTo: "54 000 ₽",
  themeColor: "#14213F",
} as const;

export const contacts = {
  phone: "+7 901 571 43 50",
  email: "info@kursantplus.ru",
  address: "г. Москва",
  schedule: {
    hours: "11:00 - 20:00",
    workdays: "ВТ - СБ",
    weekends: "ВС - ПН",
    note: "выходные дни",
  },
  messengers: ["WhatsApp", "Telegram"],
} as const;

export const trustFacts = [
  "Подготовка внимательных и аккуратных водителей категории B",
  "Сопровождение ученика до сдачи экзамена в ГИБДД",
  "Дополнительные занятия для уверенного вождения после получения прав",
] as const;

export const navigation = [
  { href: "/", label: "Главная" },
  { href: "/#programs", label: "Программы", speechLabel: "Программы обучения" },
  { href: "/#pricing", label: "Тарифы" },
  { href: "/#process", label: "Обучение", speechLabel: "Как проходит обучение" },
  { href: "/#contacts", label: "Контакты" },
  {
    href: "/info",
    label: "Сведения",
  },
] as const;
