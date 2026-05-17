using System;
using System.Globalization;

namespace CW3.Task6.Acmp
{
    /// <summary>
    /// Задача 6. Валютные махинации — самодостаточный файл для отправки на acmp.
    ///
    /// Скопировать всё содержимое файла (включая using-и и namespace) в окно отправки решения.
    /// Совместим с C# 7 (Mono / .NET на acmp).
    ///
    /// Формат ввода (INPUT.TXT):
    ///   N
    ///   D1 E1
    ///   D2 E2
    ///   ...
    ///   DN EN
    /// где N (1 ≤ N ≤ 5000) — количество дней, Di и Ei — курсы доллара и евро в день i.
    ///
    /// Формат вывода (OUTPUT.TXT):
    ///   Максимальное количество рублей к концу N-го дня с двумя знаками после точки.
    /// </summary>
    internal static class Acmp6
    {
        private static void Main()
        {
            var inv = CultureInfo.InvariantCulture;
            var tokens = Console.In.ReadToEnd()
                .Split(new[] { ' ', '\t', '\r', '\n' }, StringSplitOptions.RemoveEmptyEntries);

            int idx = 0;
            int n = int.Parse(tokens[idx++], inv);

            double rubles = 100.0;
            double dollars = 0.0;
            double euros = 0.0;

            for (int i = 0; i < n; i++)
            {
                double d = double.Parse(tokens[idx++], inv);
                double e = double.Parse(tokens[idx++], inv);

                double newRubles = Math.Max(rubles, Math.Max(dollars * d, euros * e));
                double newDollars = Math.Max(rubles / d, Math.Max(dollars, euros * e / d));
                double newEuros = Math.Max(rubles / e, Math.Max(dollars * d / e, euros));

                rubles = newRubles;
                dollars = newDollars;
                euros = newEuros;
            }

            Console.WriteLine(rubles.ToString("F2", inv));
        }
    }
}
