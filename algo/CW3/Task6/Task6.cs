using System.Globalization;

namespace CW3.Task6;

/// <summary>
/// Задача 6. Валютные махинации.
///
/// У Пети есть 100 рублей. На каждый из N дней известен курс рубля к доллару (Di)
/// и к евро (Ei). В течение дня Петя может свободно (без ограничений и с дробными
/// величинами) переводить деньги между рублями, долларами и евро по курсу этого дня.
/// Нужно определить, сколько максимально рублей он сможет иметь к концу N-го дня.
///
/// Решение: динамика по трём состояниям — сколько максимально рублей / долларов / евро
/// можно иметь к концу дня i. На каждый день из любого состояния можно перейти в любую
/// валюту (доллар↔евро — через рубли), поэтому формулы перехода полностью симметричны.
/// </summary>
public class Task6
{
    /// <summary>
    /// Метод для запуска решения на примере из условия.
    /// </summary>
    public static void ExecuteTask()
    {
        Console.WriteLine("################ Задача 6. Валютные махинации ################\n");

        double[][] rates =
        {
            new double[] { 1, 10 },
            new double[] { 10, 5.53 },
            new double[] { 5.53, 1.25 },
            new double[] { 6, 5 }
        };

        double answer = Solution(rates);
        Console.WriteLine("Курсы по дням (доллар, евро):");
        foreach (var r in rates)
            Console.WriteLine($"  {r[0]} {r[1]}");
        Console.WriteLine($"Максимум рублей к концу периода: {answer.ToString("F2", CultureInfo.InvariantCulture)}");
        Console.WriteLine("════════════════════════════════════════\n");
    }

    /// <summary>
    /// Основная логика решения.
    /// </summary>
    /// <param name="rates">Массив пар (курс доллара, курс евро) по каждому дню.</param>
    /// <returns>Максимальное количество рублей к концу последнего дня.</returns>
    public static double Solution(double[][] rates)
    {
        // Состояния: сколько максимально можно иметь рублей / долларов / евро
        double rubles = 100.0;
        double dollars = 0.0;
        double euros = 0.0;

        foreach (var day in rates)
        {
            double d = day[0]; // сколько рублей стоит 1 доллар
            double e = day[1]; // сколько рублей стоит 1 евро

            // Из любой валюты можно перейти в любую другую по курсу текущего дня.
            // Доллар ↔ евро выполняется через рубли, поэтому коэффициент — d/e или e/d.
            double newRubles = Math.Max(rubles, Math.Max(dollars * d, euros * e));
            double newDollars = Math.Max(rubles / d, Math.Max(dollars, euros * e / d));
            double newEuros = Math.Max(rubles / e, Math.Max(dollars * d / e, euros));

            rubles = newRubles;
            dollars = newDollars;
            euros = newEuros;
        }

        // Итоговую сумму нужно иметь именно в рублях
        return rubles;
    }
}
