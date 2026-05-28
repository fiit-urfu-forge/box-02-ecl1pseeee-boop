using CW3.Utils;

namespace CW3.Task3;

public class Tests3
{
    public static void Test()
    {
        Console.WriteLine("################ Задача 3. Танец точек ################\n");

        // Тест 1: пример из условия
        RunTest(10, new long[] { 30, 3, 14, 19, 21 }, 2, "Пример из условия");

        // Тест 2: одна точка — всегда остаётся одна
        RunTest(100, new long[] { 42 }, 1, "Единственная точка");

        // Тест 3: все точки помещаются в один интервал длины 2L
        RunTest(5, new long[] { 1, 2, 3, 4, 5 }, 1, "Все точки сливаются в одну");

        // Тест 4: два далёких кластера
        RunTest(1, new long[] { 1, 2, 3, 100, 101, 102 }, 2, "Два далёких кластера");

        // Тест 5: отрицательные координаты
        RunTest(10, new long[] { -50, -45, -5, 0, 5 }, 2, "Отрицательные координаты");

        // Тест 6: L = 0, движение невозможно — остаются все различные позиции
        RunTest(0, new long[] { 1, 2, 3 }, 3, "L = 0, все позиции различны");

        // Тест 7: L = 0, но точки уже совпадают
        RunTest(0, new long[] { 5, 5, 5 }, 1, "L = 0, точки уже в одной позиции");

        // Тест 8: граница покрытия — точка ровно на краю интервала
        RunTest(4, new long[] { 0, 5, 10 }, 2, "Граничный случай покрытия");

        // Тест 9: большие координаты, всё сливается
        RunTest(100000000, new long[] { 0, 200000000 }, 1, "Большие координаты, одна группа");

        // Тест 10: большие координаты, ничего не сливается
        RunTest(1000000, new long[] { 1000000000, -1000000000, 0 }, 3, "Большие координаты, три группы");
    }

    private static void RunTest(long l, long[] points, int expected, string testName)
    {
        Console.WriteLine($"Тест: {testName}");
        try
        {
            var profileResult = AlgorithmProfiler.Profile("Танец точек", () =>
                Task3.Solution(l, points));

            int result = profileResult.Result!.FirstOrDefault();

            Console.WriteLine($"Входные данные: L = {l}, точки = [{string.Join(" ", points)}]");
            if (result == expected)
                Console.WriteLine("Тест пройден успешно!");
            else
                Console.WriteLine($"ПРОВАЛЕНО! Ожидалось: {expected}, получено: {result}");

            Console.WriteLine(profileResult.ToString());
            Console.WriteLine("════════════════════════════════════════\n");
        }
        catch (Exception ex)
        {
            Console.WriteLine($"ERROR: {ex.Message}");
        }
    }
}
